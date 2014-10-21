/**
 * @namespace M.mod_hsuforum
 * @author Mark Nielsen
 */
M.mod_hsuforum = M.mod_hsuforum || {};

/**
 * Set toggle link label and accessibility stuff on ajax reponse.
 *
 * @param link
 * @author Guy Thomas
 */
M.mod_hsuforum.onToggleResponse = function(link) {
    var active,
        status,
        title,
        svgTitle;

    link.toggleClass('hsuforum_toggled');

    if (link.getAttribute('aria-pressed') == 'true') {
        link.setAttribute('aria-pressed', false);
        active = false;
    } else {
        link.setAttribute('aria-pressed', true);
        active = true;
    }

    // Set new link title;
    status = active ? 'toggled' : 'toggle';
    title = M.util.get_string(status+':'+link.getData('toggletype'),'hsuforum');
    svgTitle = link.one('svg title');
    svgTitle.set('text', title);
}

M.mod_hsuforum.toggleStatesApplied = false;

/**
 * Apply toggle state
 * @param Y
 *
 * @author Mark Neilsen / Guy Thomas
 */
M.mod_hsuforum.applyToggleState = function(Y) {
    // @todo - Get rid of this check by making sure that lib.php and renderer.php only call this once.
    if (M.mod_hsuforum.toggleStatesApplied) {
        return;
    }
    M.mod_hsuforum.toggleStatesApplied = true;
    if (Y.all('.mod_hsuforum_posts_container').isEmpty()) {
        return;
    }
    // We bind to document otherwise screen readers read everything as clickable.
    Y.delegate('click', function(e) {
        var link = e.currentTarget;
        e.preventDefault();
        e.stopPropagation();

        M.mod_hsuforum.io(Y, link.get('href'), function() {
            M.mod_hsuforum.onToggleResponse(link);
        });
    }, document, 'a.hsuforum_flag, a.hsuforum_discussion_subscribe');
}

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

/**
 * @author Mark Nielsen
 */
M.mod_hsuforum.init_modform = function(Y, HSUFORUM_GRADETYPE_MANUAL) {
    var gradetype = Y.one('.path-mod-hsuforum select[name="gradetype"]');

    if (gradetype) {
        var warning = Y.Node.create('<span id="gradetype_warning" class="hidden">' + M.str.hsuforum.manualwarning + '</span>');
        gradetype.get('parentNode').appendChild(warning);

        var updateMessage = function() {
            if (gradetype.get('value') == HSUFORUM_GRADETYPE_MANUAL) {
                warning.removeClass('hidden');
            } else {
                warning.addClass('hidden');
            }
        };

        // Init the view
        updateMessage();

        // Update view on change
        gradetype.on('change', function() {
            updateMessage();
        });
    }
};
