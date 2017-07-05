
var Login = (function() {
    "use strict";
    return {}
})();

$(document).ready(function() {
    
    $(document.body).on('click', "[data-remote!=''][data-remote]", function(event) {
        event.preventDefault();
        var options = JSON.parse(JSON.stringify($(this).data()));
    });

    $(document.body).on('click', "[data-function!=''][data-function]", function (event) {
        event.preventDefault();
        var btn = this;
        var functionName = $(this).data('function');
        var fn = getFunctionFromString(functionName);
        var sleep = 0;
        var info = $(this).data();
        for (k in info) {
            info[k] = $(this).attr('data-' + k);
        }
        if (typeof fn === 'function') {

            if ($(this).data('functionDelay')) {
                var sleep = parseInt($(this).data('functionDelay'));
            }

            setTimeout(function () {
                console.log('Executing function: ' + functionName + ' after delay of ' + sleep + ' milliseconds');
                fn(info, btn);
            }, sleep);

        } else {
            console.log('No such function: ' + functionName);
        }
    });

    //Westbase.initView();

    window.getFunctionFromString = function (string) {
        var scope = window;
        var scopeSplit = string.split('.');
        for (i = 0; i < scopeSplit.length - 1; i++) {
            scope = scope[scopeSplit[i]];
            if (scope === undefined) return;
        }
        return scope[scopeSplit[scopeSplit.length - 1]];
    };


});

