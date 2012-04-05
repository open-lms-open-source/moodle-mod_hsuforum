/**
 * @namespace M.mod_hsuforum
 * @author Mark Nielsen
 */
M.mod_hsuforum = M.mod_hsuforum || {};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_flags = function(Y) {
    var nodes = Y.all('.mod_hsuforum_posts_container');
    if (nodes) {
        nodes.each(function(node) {
            node.delegate('click', function(e) {
                var link = e.target;
                if (e.target.test('img')) {
                    link = e.target.ancestor('a.hsuforum_flag');
                }
                if (!link) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();

                M.mod_hsuforum.io(Y, link.get('href'), function() {
                    link.toggleClass('hsuforum_flag_active');
                });
            }, 'a.hsuforum_flag');
        });
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.io = function(Y, url, successCallback, failureCallback) {
    Y.io(url, {
        on: {
            success: function(id, o) {
                M.mod_hsuforum.io_success_handler(Y, o, successCallback);
            },
            failure: function() {
                M.mod_hsuforum.io_failure_handler(Y, failureCallback);
            }
        }
    });
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.io_success_handler = function(Y, response, callback) {
    var data = {};
    if (response.responseText) {
        try {
            data = Y.JSON.parse(response.responseText);
            if (data.error) {
                alert(data.error);
                if (window.console !== undefined && console.error !== undefined) {
                    console.error(data.error);
                    console.error(data.stacktrace);
                    console.error(data.debuginfo);
                }
                return;
            }
        } catch (ex) {
            alert(M.str.hsuforum.jsondecodeerror);
            return;
        }
    }
    if (callback) {
        callback(data);
    }
};

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.io_failure_handler = function(Y, callback) {
    alert(M.str.hsuforum.ajaxrequesterror);

    if (callback) {
        callback();
    }
};