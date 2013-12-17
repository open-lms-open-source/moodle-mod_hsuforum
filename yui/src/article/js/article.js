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
     * Maintains an aria live log
     *
     * @attribute liveLog
     * @type M.mod_hsuforum.init_livelog
     * @readOnly
     */
    liveLog: { readOnly: true }
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
            if (Y.one(SELECTORS.SEARCH_PAGE) !== null) {
                Y.log('Not binding event handlers on search page', 'info', 'Article');
                return;
            }
            var rootNode = Y.one(SELECTORS.CONTAINER);
            if (rootNode === null) {
                Y.log('Failed to bind event handlers', 'error', 'Article');
                return;
            }
            var dom     = this.get('dom'),
                form    = this.get('form'),
                router  = this.get('router'),
                addNode = Y.one(SELECTORS.ADD_DISCUSSION);

            // We bind to document otherwise screen readers read everything as clickable.
            Y.delegate('click', this.handleViewNextDiscussion, document, SELECTORS.DISCUSSION_NEXT, this);
            Y.delegate('click', form.handleCancelForm, document, SELECTORS.LINK_CANCEL, form);
            Y.delegate('click', router.handleRoute, document, SELECTORS.CONTAINER_LINKS, router);
            Y.delegate('click', dom.handleViewRating, document, SELECTORS.RATE_POPUP, dom);
            Y.delegate('click', function(e) {
                // On discussion close, focus on the closed discussion.
                e.target.ancestor(SELECTORS.DISCUSSION).focus();
                this.get('liveLog').logText(M.str.mod_hsuforum.discussionclosed);
            }, document, SELECTORS.DISCUSSION_CLOSE, this);

            Y.delegate('click', function() {
                // On discussion open, log that it was loaded
                this.get('liveLog').logText(M.str.mod_hsuforum.discussionloaded);
            }, document, [SELECTORS.DISCUSSION_VIEW, SELECTORS.DISCUSSION_NEXT, SELECTORS.DISCUSSION_PREV].join(', '), this);

            // Submit handlers.
            rootNode.delegate('submit', form.handleFormSubmit, SELECTORS.FORM, form);
            if (addNode instanceof Y.Node) {
                addNode.on('submit', router.handleAddDiscussionRoute, router);
            }

            // On post created, update HTML, URL and log.
            form.on(EVENTS.POST_CREATED, dom.handleUpdateDiscussion, dom);
            form.on(EVENTS.POST_CREATED, router.handleViewDiscussion, router);
            form.on(EVENTS.POST_CREATED, this.handleLiveLog, this);

            // On post updated, update HTML and URL and log.
            form.on(EVENTS.POST_UPDATED, dom.handleUpdateDiscussion, dom);
            form.on(EVENTS.POST_UPDATED, router.handleViewDiscussion, router);
            form.on(EVENTS.POST_UPDATED, this.handleLiveLog, this);

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
            this.on(EVENTS.POST_DELETED, this.handleLiveLog, this);

            // On form cancel, update the URL to view the discussion/post.
            form.on(EVENTS.FORM_CANCELED, router.handleViewDiscussion, router);
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
            this.get('dom').ensurePostsExist(node, function() {
                if (!node.hasClass(CSS.DISCUSSION_EXPANDED)) {
                    this.collapseAllDiscussions();
                    this.expandDiscussion(node);
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
            }, this);
        },

        /**
         * Load more discussions when navigating
         * to the next discussion and there are
         * none.
         *
         * @method handleViewNextDiscussion
         * @param e
         */
        handleViewNextDiscussion: function(e) {
            var node = e.currentTarget.ancestor(SELECTORS.DISCUSSION);
            if (!node.next().test(SELECTORS.DISCUSSION) && this.canLoadMoreDiscussions()) {
                var loadNode = Y.one(SELECTORS.LOAD_MORE);
                if (loadNode === null) {
                    return;
                }
                // Stop the router from handling.
                e.preventDefault();
                e.stopImmediatePropagation();

                var params = Y.QueryString.parse(loadNode.get('href').split('?')[1]);
                this.loadPage(params.page, function() {
                    this.get('router').routeUrl(e.currentTarget.get('href'));
                }, this);
            }
        },

        /**
         * Load a page of discussions.
         *
         * @method loadPage
         * @param page
         * @param {Function} [fn]
         * @param [context]
         */
        loadPage: function(page, fn, context) {
            var loadNode = Y.one(SELECTORS.LOAD_MORE);
            if (loadNode === null) {
                Y.log('Page loading disabled', 'info', 'Article');
                return;
            }
            Y.log('Loading page: ' + page, 'info', 'Article');

            var discussions  = Y.all(SELECTORS.DISCUSSION);
            var total        = parseInt(loadNode.getData('total'), 10);
            var perpage      = parseInt(loadNode.getData('perpage'), 10);
            var displayCount = (page * perpage) + perpage;

            // Make sure we don't try to display more than exist.
            if (displayCount > total) {
                displayCount = total;
            }
            if (displayCount > discussions.size()) {
                var morePages = Math.ceil((displayCount - discussions.size()) / perpage);
                morePages--; // Because we start at zero.

                if (morePages >= 0) {
                    this._loadAllPages(page, perpage, morePages, function() {
                        Y.log('Done loading pages, updating load more link');
                        // Update the load more link to load the next page.
                        var href = loadNode.getAttribute('href').replace(/page=\d+/, 'page=' + (page + 1));
                        loadNode.setAttribute('href', href);

                        // Need to hide if no more to load.
                        if (displayCount >= total) {
                            loadNode.hide();
                        } else {
                            // loadNode.show();
                            loadNode.setStyle('display', 'block');
                        }
                        if (fn) {
                            fn.call(context);
                        } else {
                            Y.all(SELECTORS.DISCUSSION).item(displayCount + 1 - perpage).scrollIntoView(true).focus();
                        }
                    }, this);
                }
            } else if (fn) {
                fn.call(context);
            }
        },

        /**
         * Internal method - keeps loading pages of discussions
         * until we are at the page we want.
         *
         * @method _loadAllPages
         * @param page
         * @param perpage
         * @param morePages
         * @param fn
         * @param context
         * @private
         */
        _loadAllPages: function(page, perpage, morePages, fn, context) {
            this.get('dom').loadMoreDiscussions(page - morePages, perpage, function() {
                var left = morePages - 1;
                if (left >= 0) {
                    this._loadAllPages(page, perpage, left, fn, context);
                } else {
                    fn.call(context);
                }
            }, this);
        },

        /**
         * Expand a discussion
         *
         * @method expandDiscussion
         * @param discussionNode
         */
        expandDiscussion: function(discussionNode) {
            Y.log('Expanding discussion: ' + discussionNode.getData('discussionid'), 'info', 'Article');

            Y.one(SELECTORS.CONTAINER).all(SELECTORS.DISCUSSION)
                .setAttribute('aria-hidden', 'true')
                .setAttribute('aria-expanded', 'false');

            discussionNode.addClass(CSS.DISCUSSION_EXPANDED)
                .setAttribute('aria-hidden', 'false')
                .setAttribute('aria-expanded', 'true')
                .scrollIntoView(true);

            this.get('form').attachFormWarnings();
        },

        /**
         * Collapse all discussions
         * @method collapseAllDiscussions
         */
        collapseAllDiscussions: function() {
            Y.log('Collapsing all discussions', 'info', 'Article');

            Y.one(SELECTORS.CONTAINER).all(SELECTORS.DISCUSSION)
                .removeClass(CSS.DISCUSSION_EXPANDED)
                .setAttribute('aria-hidden', 'false')
                .setAttribute('aria-expanded', 'false');
        },

        /**
         * Determine if we can load more discussions
         *
         * @method canLoadMoreDiscussions
         * @returns {boolean}
         */
        canLoadMoreDiscussions: function() {
            var loadNode = Y.one(SELECTORS.LOAD_MORE);
            if (loadNode === null) {
                return false;
            }
            return loadNode.getStyle('display') !== 'none';
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
