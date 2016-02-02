/**
 * DOM Updater
 *
 * @module moodle-mod_hsuforum-dom
 */

/**
 * Handles updating forum DOM structures.
 *
 * @constructor
 * @namespace M.mod_hsuforum
 * @class Dom
 * @extends Y.Base
 */
function DOM() {
    DOM.superclass.constructor.apply(this, arguments);
}

DOM.NAME = 'moodle-mod_hsuforum-dom';

DOM.ATTRS = {
    /**
     * Used for requests
     *
     * @attribute io
     * @type M.mod_hsuforum.Io
     * @required
     */
    io: { value: null }
};

Y.extend(DOM, Y.Base,
    {
        /**
         * Flag currently displayed rating widgets as processed
         * and initialize existing menus.
         *
         * @method initializer
         */
        initializer: function() {
            // Any ratings initially on the page will already be processed.
            Y.all(SELECTORS.RATE).addClass('processed');
            // Initialize current menu options.
            this.initOptionMenus();
        },

        /**
         * Initialize thread JS features that are not handled by
         * delegates.
         *
         * @method initFeatures
         */
        initFeatures: function() {
            this.initOptionMenus();
            this.initRatings();
        },

        /**
         * Wire up ratings that have been dynamically added to the page.
         *
         * @method initRatings
         */
        initRatings: function() {
            Y.all(SELECTORS.RATE).each(function(node) {
                if (node.hasClass('processed')) {
                    return;
                }
                M.core_rating.Y = Y;
                node.all('select.postratingmenu').each(M.core_rating.attach_rating_events, M.core_rating);
                node.all('input.postratingmenusubmit').setStyle('display', 'none');
                node.addClass('processed');
            });
        },

        /**
         * Initialize option menus.
         *
         * @method initOptionMenus
         */
        initOptionMenus: function() {
            Y.all(SELECTORS.OPTIONS_TO_PROCESS).each(function(node) {
                node.removeClass('unprocessed');

                var menu = new Y.YUI2.widget.Menu(node.generateID(), { lazyLoad: true });

                // Render to container otherwise tool region gets wonky huge!
                menu.render(Y.one(SELECTORS.CONTAINER).generateID());

                Y.one('#' + node.getData('controller')).on('click', function(e) {
                    e.preventDefault();
                    menu.cfg.setProperty('y', e.currentTarget.getY() + e.currentTarget.get('offsetHeight'));
                    menu.cfg.setProperty('x', e.currentTarget.getX());
                    menu.show();
                });
            });
        },

        /**
         * For dynamically loaded ratings, we need to handle the view
         * ratings pop-up manually.
         *
         * @method handleViewRating
         * @param e
         */
        handleViewRating: function(e) {
            if (e.currentTarget.ancestor('.helplink') !== null) {
                return; // Ignore help link.
            }
            e.preventDefault();
            openpopup(e, {
                url: e.currentTarget.get('href') + '&popup=1',
                name: 'ratings',
                options: "height=400,width=600,top=0,left=0,menubar=0,location=0," +
                    "scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent"
            });
        },

        /**
         * Mark a post as read
         *
         * @method markPostAsRead
         * @param {Integer} postid
         * @param {Function} fn
         * @param {Object} context Specifies what 'this' refers to.
         */
        markPostAsRead: function(postid, fn, context) {
            this.get('io').send({
                postid: postid,
                action: 'markread'
            }, fn, context);
        },

        /**
         * Can change the discussion count displayed to the user.
         *
         * Method name is misleading, you can also decrement it
         * by passing negative numbers.
         *
         * @method incrementDiscussionCount
         * @param {Integer} increment
         */
        incrementDiscussionCount: function(increment) {
            // Update number of discussions.
            var countNode = Y.one(SELECTORS.DISCUSSION_COUNT);
            if (countNode !== null) {
                // Increment the count and update display.
                countNode.setData('count', parseInt(countNode.getData('count'), 10) + increment);
                countNode.setHTML(M.util.get_string('xdiscussions', 'mod_hsuforum', countNode.getData('count')));
            }
        },

        /**
         * Display a notification
         *
         * @method displayNotification
         * @param {String} html
         */
        displayNotification: function(html) {
            var node = Y.Node.create(html);
            Y.one(SELECTORS.NOTIFICATION).append(node);

            setTimeout(function() {
                node.remove(true);
            }, 10000);
        },

        /**
         * Displays a notification from an event
         *
         * @method handleNotification
         * @param e
         */
        handleNotification: function(e) {
            if (Y.Lang.isString(e.notificationhtml) && e.notificationhtml.trim().length > 0) {
                this.displayNotification(e.notificationhtml);
            }
        },

        /**
         * Update discussion HTML
         *
         * @method handleUpdateDiscussion
         * @param e
         */
        handleUpdateDiscussion: function (e) {
            // Put date fields back to original place in DOM.
            Y.log('Updating discussion HTML to include: ' + e.discussionid, 'info', 'Dom');
            var node = Y.one('#discussionsview');
            if (node) {
                // We are viewing all disussions on one page (view.php).
                node.setHTML(e.html);
            } else {
                // We are viewing a single discussion with the replies underneath.
                node = Y.one(SELECTORS.DISCUSSION_BY_ID.replace('%d', e.discussionid));
                if (node) {
                    // Updating existing discussion.
                    node.replace(e.html);
                    this.initRatings();
                } else {
                    // Adding new discussion.
                    Y.one(SELECTORS.DISCUSSION_TARGET).insert(e.html, 'after');
                }
            }
        },

        /**
         * Discussion created event handler
         *
         * Some extra tasks needed on discussion created
         *
         * @method handleDiscussionCreated
         */
        handleDiscussionCreated: function() {
            // Remove no discussions message if on the page.
            if (Y.one(SELECTORS.NO_DISCUSSIONS)) {
                Y.one(SELECTORS.NO_DISCUSSIONS).remove();
            }
        },

        /**
         * Either redirect because we are viewing a single discussion
         * that has just been deleted OR remove the discussion
         * from the page and update navigation links on the
         * surrounding discussions.
         *
         * @method handleDiscussionDeleted
         * @param e
         */
        handleDiscussionDeleted: function(e) {
            var node = Y.one(SELECTORS.POST_BY_ID.replace('%d', e.postid));
            if (node === null || !node.hasAttribute('data-isdiscussion')) {
                return;
            }
            if (Y.one(SELECTORS.DISCUSSIONS)) {
                node.remove(true);
                this.incrementDiscussionCount(-1);
                Y.one(SELECTORS.DISCUSSION_COUNT).focus();
            } else {
                // Redirect because we are viewing a single discussion.
                window.location.href = e.redirecturl;
            }
        }
    }
);

M.mod_hsuforum.Dom = DOM;
