/**
 * Forum Article View
 *
 * @module moodle-mod_hsuforum-article
 */

/**
 * Handles updating forum article structure
 *
 * @constructor
 * @namespace M.mod_hsuforum
 * @class Article
 * @extends Y.Base
 */
function ARTICLE() {
    ARTICLE.superclass.constructor.apply(this, arguments);
}

ARTICLE.NAME = NAME;

ARTICLE.ATTRS = {
    /**
     * Current context ID, used for AJAX requests
     *
     * @attribute contextId
     * @type Number
     * @default undefined
     * @required
     */
    contextId: { value: undefined },

    /**
     * Used for REST calls
     *
     * @attribute io
     * @type M.mod_hsuforum.Io
     * @readOnly
     */
    io: { readOnly: true },

    /**
     * Used primarily for updating the DOM
     *
     * @attribute dom
     * @type M.mod_hsuforum.Dom
     * @readOnly
     */
    dom: { readOnly: true },

    /**
     * Used for routing URLs within the same page
     *
     * @attribute router
     * @type M.mod_hsuforum.Router
     * @readOnly
     */
    router: { readOnly: true },

    /**
     * Displays, hides and submits forms
     *
     * @attribute form
     * @type M.mod_hsuforum.Form
     * @readOnly
     */
    form: { readOnly: true },

    /**
     * Maintains an aria live log.
     *
     * @attribute liveLog
     * @type M.mod_hsuforum.init_livelog
     * @readOnly
     */
    liveLog: { readOnly: true },

    /**
     * The show advanced edit link that was clicked most recently,
     */
    currentEditLink: null
};

Y.extend(ARTICLE, Y.Base,
    {
        /**
         * Setup the app
         */
        initializer: function() {
            this._set('router', new M.mod_hsuforum.Router({article: this, html5: false}));
            this._set('io', new M.mod_hsuforum.Io({contextId: this.get('contextId')}));
            this._set('dom', new M.mod_hsuforum.Dom({io: this.get('io')}));
            this._set('form', new M.mod_hsuforum.Form({io: this.get('io')}));
            this._set('liveLog', M.mod_hsuforum.init_livelog());
            this.bind();
            // this.get('router').dispatch();
        },

        /**
         * Bind all event listeners
         * @method bind
         */
        bind: function() {
            var firstUnreadPost = document.getElementsByClassName("hsuforum-post-unread")[0];
            if(firstUnreadPost && location.hash === '#unread') {
                // get the post parent to focus on
                var post = document.getElementById(firstUnreadPost.id).parentNode;
                // Workaround issues that IE has with negative margins in themes.
                if (navigator.userAgent.match(/Trident|MSIE/)) {
                    var y, e;
                    y = post.offsetTop;
                    e = post;
                    while ((e = e.offsetParent)) {
                        y += e.offsetTop;
                    }
                    window.scrollTo(0, y);
                } else {
                    post.scrollIntoView();
                }
                post.focus();
            }

            if (Y.one(SELECTORS.SEARCH_PAGE) !== null) {
                Y.log('Not binding event handlers on search page', 'info', 'Article');
                return;
            }

            var dom     = this.get('dom'),
                form    = this.get('form'),
                router  = this.get('router');

            // Implement toggling for post to all groups checkbox and groups select
            var posttoallgroups = '.hsuforum-discussion input[name="posttomygroups"]';
            Y.delegate('click', form.handlePostToGroupsToggle, document, posttoallgroups, form);

            // Implement toggling for the start and time elements for discussions.
            Y.delegate('click', form.handleTimeToggle, document, '#id_timestart_enabled', form);
            Y.delegate('click', form.handleTimeToggle, document, '#id_timeend_enabled', form);

            // We bind to document otherwise screen readers read everything as clickable.
            Y.delegate('click', form.handleCancelForm, document, SELECTORS.LINK_CANCEL, form);
            Y.delegate('click', router.handleRoute, document, SELECTORS.CONTAINER_LINKS, router);
            Y.delegate('click', dom.handleViewRating, document, SELECTORS.RATE_POPUP, dom);

            // We bind to document for these buttons as they get re-added on each discussion addition.
            Y.delegate('submit', form.handleFormSubmit, document, SELECTORS.FORM, form);
            Y.delegate('click', router.handleAddDiscussionRoute, document, SELECTORS.ADD_DISCUSSION, router);
            Y.delegate('click', form.sendInProgressData, document, SELECTORS.FORM_ADVANCED, form);
            // On post created, update HTML, URL and log.
            form.on(EVENTS.POST_CREATED, dom.handleUpdateDiscussion, dom);
            form.on(EVENTS.POST_CREATED, dom.handleNotification, dom);
            form.on(EVENTS.POST_CREATED, router.handleViewDiscussion, router);
            form.on(EVENTS.POST_CREATED, this.handleLiveLog, this);

            // On post updated, update HTML and URL and log.
            form.on(EVENTS.POST_UPDATED, this.handlePostUpdated, this);

            // On discussion created, update HTML, display notification, update URL and log it.
            form.on(EVENTS.DISCUSSION_CREATED, dom.handleUpdateDiscussion, dom);
            form.on(EVENTS.DISCUSSION_CREATED, dom.handleDiscussionCreated, dom);
            form.on(EVENTS.DISCUSSION_CREATED, dom.handleNotification, dom);
            form.on(EVENTS.DISCUSSION_CREATED, router.handleViewDiscussion, router);
            form.on(EVENTS.DISCUSSION_CREATED, this.handleLiveLog, this);

            // On discussion delete, update HTML (may redirect!), display notification and log it.
            this.on(EVENTS.DISCUSSION_DELETED, dom.handleDiscussionDeleted, dom);
            this.on(EVENTS.DISCUSSION_DELETED, dom.handleNotification, dom);
            this.on(EVENTS.DISCUSSION_DELETED, this.handleLiveLog, this);

            // On post deleted, update HTML, URL and log.
            this.on(EVENTS.POST_DELETED, dom.handleUpdateDiscussion, dom);
            this.on(EVENTS.POST_DELETED, router.handleViewDiscussion, router);
            this.on(EVENTS.POST_DELETED, dom.handleNotification, dom);
            this.on(EVENTS.POST_DELETED, this.handleLiveLog, this);

            // On form cancel, update the URL to view the discussion/post.
            form.on(EVENTS.FORM_CANCELED, router.handleViewDiscussion, router);
        },

        handlePostUpdated: function(e) {
            var dom     = this.get('dom'),
                form    = this.get('form'),
                router  = this.get('router');
            dom.handleUpdateDiscussion(e);
            router.handleViewDiscussion(e);
            dom.handleNotification(e);
            this.handleLiveLog(e);
        },

        /**
         * Inspects event object for livelog and logs it if found
         * @method handleLiveLog
         * @param e
         */
        handleLiveLog: function(e) {
            if (Y.Lang.isString(e.livelog)) {
                this.get('liveLog').logText(e.livelog);
            }
        },

        /**
         * View a discussion
         *
         * @method viewDiscussion
         * @param discussionid
         * @param [postid]
         */
        viewDiscussion: function(discussionid, postid) {
            var node = Y.one(SELECTORS.DISCUSSION_BY_ID.replace('%d', discussionid));
            if (!(node instanceof Y.Node)) {
                Y.log('Cannot view discussion because discussion node not found', 'error', 'Article');
                return;
            }
            if (!Y.Lang.isUndefined(postid)) {
                var postNode = Y.one(SELECTORS.POST_BY_ID.replace('%d', postid));
                if (postNode === null || postNode.hasAttribute('data-isdiscussion')) {
                    node.focus();
                } else {
                    postNode.get('parentNode').focus();
                }
            } else {
                node.focus();
            }
        },

        /**
         * Confirm deletion of a post
         *
         * @method confirmDeletePost
         * @param {Integer} postId
         */
        confirmDeletePost: function(postId) {
            var node = Y.one(SELECTORS.POST_BY_ID.replace('%d', postId));
            if (node === null) {
                return;
            }
            if (window.confirm(M.str.mod_hsuforum.deletesure) === true) {
                this.deletePost(postId);
            }
        },

        /**
         * Delete a post
         *
         * @method deletePost
         * @param {Integer} postId
         */
        deletePost: function(postId) {
            var node = Y.one(SELECTORS.POST_BY_ID.replace('%d', postId));
            if (node === null) {
                return;
            }
            Y.log('Deleting post: ' + postId);

            this.get('io').send({
                postid: postId,
                sesskey: M.cfg.sesskey,
                action: 'delete_post'
            }, function(data) {
                if (node.hasAttribute('data-isdiscussion')) {
                    this.fire(EVENTS.DISCUSSION_DELETED, data);
                } else {
                    this.fire(EVENTS.POST_DELETED, data);
                }
            }, this);
        }
    }
);

M.mod_hsuforum.Article = ARTICLE;
M.mod_hsuforum.init_article = function(config) {
    new ARTICLE(config);
};

/**
 * Trigger click event.
 * @param el
 */
M.mod_hsuforum.dispatchClick = function(el) {
    if (document.createEvent) {
        var event = new MouseEvent('click', {
            'view': window,
            'bubbles': true,
            'cancelable': true
        });
        el.dispatchEvent(event);
    } else if (el.fireEvent) {
        el.fireEvent('onclick');
    }
};
