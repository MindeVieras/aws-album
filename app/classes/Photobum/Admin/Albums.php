<?php

namespace Photobum\Admin;

use Photobum\Utilities\General;
use DB\SQL\Mapper;
use Photobum\Config;
use \DateTime;

use Photobum\Utilities\Aws\S3\Copy;
use Photobum\Utilities\Aws\S3\Move;
use Photobum\Utilities\Aws\S3\Delete;

class Albums extends Admin{

    public function __construct(){
        parent::__construct();
        $this->initOrm('albums');
        $this->model->url = 'SELECT url FROM urls WHERE urls.type_id = albums.id AND urls.type = \'album\'';
        $this->twig->onReady('PhotobumAdmin.albumsReady');
        $this->page['title']= 'Albums Manager';
        $this->page['section']= 'albums';
        $this->page['body_class']= 'albums';
        //ddd($color);
    }

    public function view($params){
        $this->auth();
        $this->twig->onReady('PhotobumAdmin.viewAlbums');
        $this->years = $this->db->exec("SELECT DISTINCT(YEAR(start_date)) as year FROM albums ORDER BY start_date DESC");
        $template = $this->twig->loadTemplate('Admin/Album/view.html');
        echo $template->render([
            'page' => $this->page,
            'years' => $this->years,
            'user' => $this->user
        ]);
    }

    public function get($params){

        $this->auth();
        //ddd($params);
        $date = $params['date'];
        $template = $this->twig->loadTemplate('Admin/Album/list-item.html');
        echo $template->render([
            'page' => $this->page,
            'data' => $this->getAlbums($date),
            'user' => $this->user
        ]);
    }

    public function add(){
        
        $this->auth();
        
        if ($this->f3->get('VERB') == 'POST') {
            $item = $this->f3->get('POST');

            if(!$item['name']){General::flushJsonResponse(['ack'=>'error', 'msg'=>'Album name is required']);}

            if(!$item['start_date']){General::flushJsonResponse(['ack'=>'error', 'msg'=>'Album date is required']);}

            $this->model->reset();

            $date = new DateTime($item['start_date']);
            $date_path = $date->format('Y/m/d');
            $name = \Web::instance()->slug($item['name']);

            $name_date_path = $date_path.'/'.$name;
            $media_path = 'albums/'.$name_date_path;
            
            // Get album color id
            $cid = $this->db->exec('SELECT id FROM colors WHERE code = ? AND type = \'album\'', $item['color']);

            $id = $item['id'];
            $editMode = $id ? true : false;

            if ($editMode) {

                $this->model->load(['id=?', $id]);

                // delete locations before it gets saved
                $this->db->exec("DELETE FROM locations WHERE album_id = '$id'");
                // delete persons relations before save
                $this->db->exec("DELETE FROM persons_rel WHERE album_id = '$id'");

                // remove unvanted media files and db
                if(!empty($item['files_remove'])){
                    // Make array of files Urls on S3
                    foreach ($item['files_remove'] as $f) {
                        $f_url = $f['value'];
                        // Remove DB entries
                        $this->db->exec("DELETE FROM media WHERE file_url = '$f_url'");
                        
                        // Org file
                        $keys[] = array('Key' => $f_url);

                        // image styles array
                        if ($f['file_type'] == 'image'){                        
                            foreach ($this->img_styles as $s) {
                                $style_file = str_replace('albums/', 'styles/'.$s['name'].'/', $f_url);
                                $keys[] = array('Key' => $style_file);
                            }
                        }

                        // Video sizes array
                        if ($f['file_type'] == 'video'){                        
                            foreach ($this->video_sizes as $s) {
                                
                                $vthumb = explode('.', $f['filename']);
                                $videoThumbName = $vthumb[0];

                                $size_file = str_replace('albums/', 'videos/'.$s.'/', $f_url);

                                $st = str_replace($f['filename'], $videoThumbName.'-00001.jpg', $f_url);
                                $size_thumb = str_replace('albums/', 'videos/'.$s.'/', $st);
                                
                                $keys[] = array('Key' => $size_file);
                                $keys[] = array('Key' => $size_thumb);
                            }
                        }
                    }
                    //sd($keys);  
                    // Remove files from S3
                    (new Delete())->deleteObjects($keys);
                }

                //rename dir if album name or date chaged
                $old_name = \Web::instance()->slug($this->model->name);
                $date = new DateTime($this->model->start_date);
                $old_date = $date->format('Y/m/d');
                $old_path = $old_date.'/'.$old_name;

                $nameChanged = ($old_path != $name_date_path) ? true : false;

                if($nameChanged && (!empty($item['album_images_db']))){
                    
                    // get db media files
                    $db_files = $this->db->exec('SELECT * FROM media WHERE type_id = ?', $id);

                    $med = $this->initOrm('media', true);
                    foreach ($db_files as $row) {
                        // Rename db entries
                        $med->load(['type_id=? and file_url=?', $id, $row['file_url']]);
                        $new_url = str_replace($old_path, $name_date_path, $med->file_url);
                        $med->file_url = $new_url;
                        $med->save();

                        // Make move array for S3
                        $files['src'] = $row['file_url'];
                        $files['type'] = $row['file_type'];
                        $tmp_files[] = $files;
                    }

                    // Move files on S3
                    (new Move())->moveObjects($tmp_files, $name_date_path);
                    //sd($movres);
                }
            }

            $this->model->name = $item['name'];
            $this->model->start_date = $item['start_date'];
            $this->model->end_date = $item['end_date'];
            $this->model->location_name = $item['location_name'];
            $this->model->body = $item['body'];
            $this->model->color = $cid[0]['id'] ? $cid[0]['id'] : 1;
            $this->model->private = intval($item['private'] == 'false');
            $this->model->save();

            // rename files to dir and save media urls
            if(!empty($item['album_images'])){

                // rename files
                foreach ($item['album_images'] as $file) {

                    $file_path = $media_path.'/'.$file['filename'];

                    //save media urls
                    $med = $this->initOrm('media', true);
                    $med->file_url = $file_path;
                    $med->file_type = $file['file_type'];
                    $med->type = 'album';
                    $med->type_id = $this->model->id;
                    $med->weight = $file['weight'];
                    $med->save();


                    $files['src'] = $file['value'];
                    $files['type'] = $file['file_type'];

                    $tmp_files[] = $files;

                }

                // Move files on S3
                (new Move())->moveObjects($tmp_files, $name_date_path);
                //sd($movres);
            }

            // save locations
            if (!empty($item['locations'])){
                foreach ($item['locations'] as $loc) {
                    $locs = $this->initOrm('locations', true);
                    $latLng = explode(',', $loc['value']);
                    $locs->lat = $latLng[0];
                    $locs->lng = $latLng[1];
                    $locs->album_id = $this->model->id;
                    $locs->save();
                }
            }

            // save persons
            if (!empty($item['album_persons'])){
                foreach ($item['album_persons'] as $per) {
                    $pers = $this->initOrm('persons_rel', true);
                    $pers->person_id = $per;
                    $pers->album_id = $this->model->id;
                    $pers->save();
                }
            }

            // save url
            $url = General::makeAlbumUrl($item['name'], $item['start_date']);
            $urls = $this->initOrm('urls', true);
            $urls->load(['type_id=? and type=\'album\'', $id]);
            $urls->url = $url['url'];
            $urls->type = 'album';
            $urls->type_id = $this->model->id;
            $urls->save();

            // flush response
            $data = ['ack' => 'ok', 'msg' => $item];
            General::flushJsonResponse($data);

        } else {
            $template = $this->twig->loadTemplate('Admin/Album/add.html');
            echo $template->render([
                'persons' => General::getPersons(),
                'color' => $this->db->exec("SELECT code FROM colors ORDER BY RAND() LIMIT 1"),
                'colors' => General::getColors(),
                'page' => $this->page
            ]);
        }
    }

    public function edit($params){
        $this->auth();
        $id = $params['id'];
        $this->model->load(['id=?', $id]);
        $cid = $this->model->color;
        $this->color = $this->db->exec("SELECT code FROM colors WHERE id = $cid");
        $template = $this->twig->loadTemplate('Admin/Album/edit.html');
        echo $template->render([
            'locations' => $this->getLocations($params['id']),
            'media' => $this->getMedia($params['id'], 1000),
            'color' => $this->color[0]['code'],
            'colors' => General::getColors(),
            'persons' => General::getPersons($params['id']),
            'item' => $this->model->cast(),
            'page' => $this->page
        ]);
    }

    public function delete($params){
        $this->auth();
        if ($this->f3->get('VERB') == 'DELETE') {
            $id = $params['id'];
                            
            // remove album
            $this->db->exec("DELETE FROM albums WHERE id = '$id'");
            // remove url
            $this->db->exec("DELETE FROM urls WHERE type = 'album' AND type_id = '$id'");
            // remove media locations
            $this->db->exec("DELETE FROM locations WHERE album_id = '$id'");
            // remove media urls
            $this->db->exec("DELETE FROM media WHERE type_id = '$id'");
            // remove persons relations
            $this->db->exec("DELETE FROM persons_rel WHERE album_id = '$id'");

            General::flushJsonResponse(['ack' => 'ok']);
        }
        General::flushJsonResponse([ack=>'Error', 'msg'=>'Could not delete item']);
    }

    private function getAlbums($date){

        $albums = $this->db->exec("SELECT 
                                        albums.*,
                                        urls.url,
                                        colors.code
                                    FROM
                                        albums
                                        JOIN urls ON albums.id = urls.type_id AND urls.type = 'album'
                                        JOIN colors ON albums.color = colors.id
                                    WHERE YEAR(albums.start_date) = '$date'
                                    ORDER BY created DESC LIMIT 200");
        //ddd($albums);

        foreach ($albums as $a) {

            $date = new DateTime($a['start_date']);
            $date_path = $date->format('Y'.DS.'m'.DS.'d');
            $name = \Web::instance()->slug($a['name']);
            $cid = $a['color'];

            $d['id'] = $a['id'];
            $d['name'] = $a['name'];
            $d['date'] = $date->format('Y-m-d H:i:s');
            $d['media_dir'] = getcwd().DS.'media'.DS.'albums'.DS.$date_path.DS.$name;
            $d['url'] = $a['url'];
            $d['color'] = $a['code'];
            $d['created'] = $a['created'];
            $d['media'] = $this->getMedia($a['id'], 2);

            $data[] = $d;
        }

        return $data;
    }

    private function getMedia($id, $limit){

        $media = $this->db->exec("SELECT * from media WHERE type_id = '$id' ORDER BY weight ASC LIMIT $limit");
        
        foreach ($media as $m) {
            $medi['id'] = $m['id'];
            $medi['url'] = $m['file_url'];
            $medi['type'] = $m['file_type'];
            $medi['name'] = basename($m['file_url']);
            $medi['weight'] = $m['weight'];
            if(file_exists(getcwd().$m['file_url'])){            
                $file_size = filesize(getcwd().$m['file_url']);
                $medi['size'] = General::formatSizeUnits($file_size);
                if($m['type'] == 'image'){
                    $exif = @exif_read_data(getcwd().$m['file_url']);
                    if($exif['DateTimeOriginal']){
                        $ex_date = new DateTime($exif['DateTimeOriginal']);
                        $date = $ex_date->format('Y-m-d H:i:s');
                        $medi['date_taken'] = $date;
                    }
                    //$medi['camera'] = $exif['Make'].' ( '.substr($exif['Model'], 0, 10).' )';
                    //$medi['date_taken'] = $exif;
                }
            }


            $md[] = $medi;
        }
        return $md;
    }

    private function getLocations($id){
        $locations = $this->db->exec("SELECT id, lat, lng from locations WHERE album_id = '$id'");
        return $locations;
    }

}