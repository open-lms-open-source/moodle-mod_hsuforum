/**
 * Forum Router
 *
 * @module moodle-mod_hsuforum-router
 */

/**
 * Handles URL routing
 *
 * @constructor
 * @namespace M.mod_hsuforum
 * @class Router
 * @extends Y.Router
 */
var ROUTER = Y.Base.create('hsuforumRouter', Y.Router, [], {
    /**
     * Disable discussion navigation links when viewing a
     * single discussion.
     *
     * @method initializer
     */
    initializer: function() {
        // If viewing a single discussion, disable router on nav links.
        if (Y.one(SELECTORS.DISCUSSIONS) === null) {
            Y.all(SELECTORS.DISCUSSION_NAV_LINKS).addClass('disable-router');
        }
    },

    /**
     * View the list of discussions.
     *
     * Handles collapsing open discussions and
     * the paging of discussions.
     *
     * @method view
     * @param req
     */
    view: function(req) {
        this.get('article').collapseAllDiscussions();

        if (!Y.Lang.isUndefined(req.query.page)) {
            this.get('article').loadPage(parseInt(req.query.page, 10));
        } else {
            this.get('article').loadPage(0);
        }
    },

    /**
     * View a discussion
     *
     * @method discussion
     * @param {Object} req
     */
    discussion: function(req) {
        this.get('article').viewDiscussion(req.query.d, req.query.postid);
    },

    /**
     * Post editing
     *
     * @method post
     * @param {Object} req
     */
    post: function(req) {
        if (!Y.Lang.isUndefined(req.query.reply)) {
            this.get('article').get('form').showReplyToForm(req.query.reply);
        } else if (!Y.Lang.isUndefined(req.query.forum)) {
            this.get('article').get('form').showAddDiscussionForm(req.query.forum);
        } else if (!Y.Lang.isUndefined(req.query['delete'])) {
            this.get('article').confirmDeletePost(req.query['delete']);
        } else if (!Y.Lang.isUndefined(req.query.edit)) {
            this.get('article').get('form').showEditForm(req.query.edit);
        } else if (!Y.Lang.isUndefined(req.query.prune)) {
            window.location.href = M.cfg.wwwroot + this.get('root') + req.path + '?' + Y.QueryString.stringify(req.query);
        }
    },

    /**
     * Handles routing of link clicks
     *
     * @param e
     */
    handleRoute: function(e) {
        // Allow the native behavior on middle/right-click, or when Ctrl or Command are pressed.
        if (e.button !== 1 || e.ctrlKey || e.metaKey || e.currentTarget.hasClass('disable-router')) {
            return;
        }
        if (this.routeUrl(e.currentTarget.get('href'))) {
            e.preventDefault();
        }
    },

    /**
     * Route a URL if possible
     *
     * @method routeUrl
     * @param {String} url
     * @returns {boolean}
     */
    routeUrl: function(url) {
        if (this.hasRoute(url)) {
            this.save(this.removeRoot(url));
            return true;
        }
        return false;
    },

    /**
     * Add discussion button handler
     *
     * @method handleAddDiscussionRoute
     * @param e
     */
    handleAddDiscussionRoute: function(e) {
        e.preventDefault();

        var formNode = e.currentTarget,
            root     = this.removeRoot(formNode.get('action')),
            forumId  = formNode.one(SELECTORS.INPUT_FORUM).get('value');

        this.save(root + '?forum=' + forumId);
    },

    /**
     * Update route to view the discussion
     *
     * Usually done after the discussion was added
     * or updated.
     *
     * @method handleViewDiscussion
     * @param e
     */
    handleViewDiscussion: function(e) {
        var path = '/discuss.php?d=' + e.discussionid;
        if (!Y.Lang.isUndefined(e.postid)) {
            path = path + '&postid=' + e.postid;
        }
        this.save(path);
    },

    /**
     * Middleware: before executing a route, hide
     * all of the open forms.
     *
     * @method hideForms
     * @param req
     * @param res
     * @param next
     */
    hideForms: function(req, res, next) {
        this.get('article').get('form').removeAllForms();
        next();
    }
}, {
    ATTRS: {
        /**
         * Used for responding to routing actions
         *
         * @attribute article
         * @type M.mod_hsuforum.Article
         * @required
         */
        article: { value: null },

        /**
         * Root URL
         *
         * @attribute root
         * @type String
         * @default '/mod/hsuforum'
         * @required
         */
        root: {
            value: '/mod/hsuforum'
        },

        /**
         * Default routes
         *
         * @attribute routes
         * @type Array
         * @required
         */
        routes: {
            value: [
                { path: '/view.php', callbacks: ['hideForms', 'view'] },
                { path: '/discuss.php', callbacks: ['hideForms', 'discussion'] },
                { path: '/post.php', callbacks: ['hideForms', 'post'] }
            ]
        }
    }
});

M.mod_hsuforum.Router = ROUTER;
