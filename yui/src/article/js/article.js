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

    io: { readOnly: true },
    dom: { readOnly: true },
    router: { readOnly: true },
    form: { readOnly: true },
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
            Y.delegate('click', router.handleRoute, document, SELECTORS.CONTAINER_LINKS, router);
            Y.delegate('click', dom.handleViewRating, document, SELECTORS.RATE_POPUP, dom);
            Y.delegate('click', function(e) {
                // On discussion close, focus on the closed discussion.
                e.target.ancestor(SELECTORS.DISCUSSION).focus();
                this.get('liveLog').logText(M.str.mod_hsuforum.discussionclosed);
            }, document, SELECTORS.DISCUSSION_CLOSE, this);

            // Sumit handlers.
            rootNode.delegate('submit', form.handleSubmitReplyTo, SELECTORS.FORM_REPLY, form);
            rootNode.delegate('submit', form.handleSubmitAddDiscussion, SELECTORS.FORM_DISCUSSION, form);
            if (addNode instanceof Y.Node) {
                addNode.on('submit', router.handleAddDiscussionRoute, router);
            }

            // Inter-module "relations" - so scandalous!
            form.on(EVENTS.POST_CREATED, dom.handlePostCreated, dom);
            form.on(EVENTS.POST_CREATED, router.handleViewDiscussion, router);

            form.on(EVENTS.DISCUSSION_CREATED, dom.handleDiscussionCreated, dom);

            this.on(EVENTS.POST_DELETE, dom.handlePostDelete, dom);
            dom.on(EVENTS.POST_DELETED, router.handleViewDiscussion, router);

            // Live logging.
            form.on(EVENTS.DISCUSSION_CREATED, this.handleLiveLog, this);
            form.on(EVENTS.POST_CREATED, this.handleLiveLog, this);
            dom.on(EVENTS.POST_DELETED, this.handleLiveLog, this);
            Y.delegate('click', function() {
                this.get('liveLog').logText(M.str.mod_hsuforum.discussionloaded);
            }, document, [SELECTORS.DISCUSSION_VIEW, SELECTORS.DISCUSSION_NEXT, SELECTORS.DISCUSSION_PREV].join(', '), this);
        },

        /**
         * Inspects event object for livelog and logs it if found
         * @param e
         */
        handleLiveLog: function(e) {
            if (Y.Lang.isString(e.livelog)) {
                this.get('liveLog').logText(e.livelog);
            }
        },

        /**
         * View a discussion
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
                this.collapseAllDiscussions();
                this.expandDiscussion(node);

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
                            loadNode.show();
                        }
                        Y.all(SELECTORS.DISCUSSION).item(displayCount - perpage).focus();

                        if (fn) {
                            fn.call(context);
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
         * @param discussionNode
         */
        expandDiscussion: function(discussionNode) {
            Y.log('Expanding discussion: ' + discussionNode.getData('discussionid'), 'info', 'Article');
            var discussions = Y.one(SELECTORS.CONTAINER).all(SELECTORS.DISCUSSION);
            discussions.each(function(discussion) {
                discussion.setAttribute('aria-hidden', 'true');
            });
            discussionNode.setAttribute('aria-hidden', 'false');
            discussionNode.addClass('hsuforum-thread-article-expanded');
            this.get('form').attachFormWarnings();
        },

        /**
         * Collapse all discussions
         */
        collapseAllDiscussions: function() {
            Y.log('Collapsing all discussions', 'info', 'Article');
            var discussions = Y.one(SELECTORS.CONTAINER).all(SELECTORS.DISCUSSION);
            discussions.each(function(discussion) {
                discussion.removeClass('hsuforum-thread-article-expanded');
                discussion.setAttribute('aria-hidden', 'false');
            });
        },

        /**
         * Determine if we can load more discussions
         * @returns {boolean}
         */
        canLoadMoreDiscussions: function() {
            var loadNode = Y.one(SELECTORS.LOAD_MORE);
            if (loadNode === null) {
                return false;
            }
            return loadNode.getStyle('display') !== 'none';
        },

        confirmDelete: function(postId) {
            var node = Y.one(SELECTORS.POST_BY_ID.replace('%d', postId));
            if (node === null) {
                return;
            }
            if (window.confirm(M.str.mod_hsuforum.deletesure) === true) {
                this.fire(EVENTS.POST_DELETE, {postid: postId});
            }
        }
    }
);

M.mod_hsuforum.Article = ARTICLE;
M.mod_hsuforum.init_article = function(config) {
    new ARTICLE(config);
};
