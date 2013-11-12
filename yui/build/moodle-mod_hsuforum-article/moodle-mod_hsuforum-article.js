YUI.add('moodle-mod_hsuforum-article', function (Y, NAME) {

var SELECTORS = {
        ADD_DISCUSSION: '#newdiscussionform',
        ADD_DISCUSSION_TARGET: '.hsuforum-add-discussion-target',
        ALL_FORMS: '.hsuforum-reply-wrapper form',
        CONTAINER: '.mod_hsuforum_posts_container',
        CONTAINER_LINKS: '.mod_hsuforum_posts_container a',
        DISCUSSION: '.hsuforum-thread-article',
        DISCUSSIONS: '.hsuforum-threads-wrapper',
        DISCUSSION_BY_ID: '.hsuforum-thread-article[data-discussionid="%d"]',
        DISCUSSION_CLOSE: '.hsuforum-thread-nav .close',
        DISCUSSION_COUNT: '.hsuforum-discussion-count',
        DISCUSSION_NAV_LINKS: '.hsuforum-thread-nav a',
        DISCUSSION_NEXT: '.hsuforum-thread-nav .next',
        DISCUSSION_PREV: '.hsuforum-thread-nav .prev',
        DISCUSSION_TARGET: '.hsuforum-new-discussion-target',
        DISCUSSION_TEMPLATE: '#hsuforum-discussion-template',
        DISCUSSION_VIEW: '.hsuforum-thread-view',
        FORM_ADVANCED: '.hsuforum-use-advanced',
        FORM_DISCUSSION: '.hsuforum-discussion',
        FORM_REPLY: '.hsuforum-reply',
        FORM_REPLY_WRAPPER: '.hsuforum-reply-wrapper',
        INPUT_FORUM: 'input[name="forum"]',
        INPUT_MESSAGE: 'textarea[name="message"]',
        INPUT_REPLY: 'input[name="reply"]',
        INPUT_SUBJECT: 'input[name="subject"]',
        LOAD_MORE: '.hsuforum-threads-load-more',
        LOAD_TARGET: '.hsuforum-threads-load-target',
        NO_DISCUSSIONS: '.forumnodiscuss',
        NOTIFICATION: '.hsuforum-notification',
        OPTIONS_TO_PROCESS: '.hsuforum-options-menu.unprocessed',
        PLACEHOLDER: '.thread-replies-placeholder',
        POSTS: '.hsuforum-thread-replies',
        POST_BY_ID: '.hsuforum-post-target[data-postid="%d"]',
        POST_TARGET: '.hsuforum-post-target',
        RATE: '.forum-post-rating',
        RATE_POPUP: '.forum-post-rating a',
        REPLY_TEMPLATE: '#hsuforum-reply-template',
        SEARCH_PAGE: '#page-mod-hsuforum-search',
        VALIDATION_ERRORS: '.hsuforum-validation-errors',
        VIEW_POSTS: '.hsuforum-view-posts'
    },
    EVENTS = {
        DISCUSSION_CREATED: 'discussion:created',
        POST_CREATED: 'post:created',
        POST_DELETE: 'post:delete',
        POST_DELETED: 'post:deleted'
    };

M.mod_hsuforum = M.mod_hsuforum || {};
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
        initializer: function() {
            // Any ratings initially on the page will already be processed.
            Y.all(SELECTORS.RATE).addClass('processed');
            // Initialize current menu options.
            this.initOptionMenus();
        },

        /**
         * Force discussion navigation links to point to each
         * other for the passed discussion, the previous discussion
         * and then next discussion.
         *
         * @method _forceNavLinks
         * @param {Integer} discussionId
         * @private
         */
        _forceNavLinks: function(discussionId) {
            var node = Y.one(SELECTORS.DISCUSSION_BY_ID.replace('%d', discussionId)),
                prev = node.previous(SELECTORS.DISCUSSION),
                next = node.next(SELECTORS.DISCUSSION);

            var updateURL = function(link, discNode) {
                var href = link.getAttribute('href').replace(/d=\d+/, 'd=' + discNode.getData('discussionid'));
                link.setAttribute('href', href)
                    .removeClass('hidden')
                    .show();
            };

            if (prev !== null) {
                // Force previous discussion to point to this discussion.
                updateURL(prev.one(SELECTORS.DISCUSSION_NEXT), node);
                updateURL(node.one(SELECTORS.DISCUSSION_PREV), prev);
            } else {
                // No previous discussion, hide prev link.
                node.one(SELECTORS.DISCUSSION_PREV).hide();
            }
            if (next !== null) {
                // Force next discussion to point to this discussion.
                updateURL(next.one(SELECTORS.DISCUSSION_PREV), node);
                updateURL(node.one(SELECTORS.DISCUSSION_NEXT), next);
            } else {
                // No next discussion, hide next link.
                node.one(SELECTORS.DISCUSSION_NEXT).hide();
            }
        },

        /**
         * Initialize thread JS features that are not handled by
         * delegates.
         */
        initFeatures: function() {
            this.initOptionMenus();
            this.initRatings();
        },

        /**
         * Wire up ratings that have been dynamically added to the page.
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
         * @param node
         * @param {Function} fn
         * @param {Object} context Specifies what 'this' refers to.
         */
        ensurePostsExist: function(node, fn, context) {
            var unread = node.hasAttribute('data-isunread');
            if (unread) {
                node.removeAttribute('data-isunread');
            }
            var viewNode = node.one(SELECTORS.PLACEHOLDER);
            if (viewNode === null) {
                this.initFeatures();
                if (unread) {
                    this.markPostAsRead(node.getData('postid'), fn, context);
                } else {
                    fn.call(context);
                }
                return;
            }

            this.get('io').send({
                discussionid: node.getData('discussionid'),
                action: 'posts_html'
            }, function(data) {
                viewNode.replace(data.html);
                this.initFeatures();
                fn.call(context);
            }, this);
        },

        /**
         * Display a notification
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
         * Post created event handler
         *
         * Grab HTML from the event and insert it.
         *
         * @param e
         */
        handlePostCreated: function (e) {
            var node = Y.one(SELECTORS.DISCUSSION_BY_ID.replace('%d', e.discussionid));
            node.replace(e.html);
        },

        /**
         * Discussion created event handler
         *
         * Grab HTML from the event and insert it.
         * Also update discussion count.
         *
         * @param e
         */
        handleDiscussionCreated: function(e) {

            // Add new discussion to the page.
            Y.one(SELECTORS.DISCUSSION_TARGET).insert(e.html, 'after');

            // Update navigation links.
            this._forceNavLinks(e.discussionid);

            // Add notification.
            this.displayNotification(e.notificationhtml);

            // Remove no discussions message if on the page.
            if (Y.one(SELECTORS.NO_DISCUSSIONS)) {
                Y.one(SELECTORS.NO_DISCUSSIONS).remove();
            }

            // Update number of discussions.
            var countNode = Y.one(SELECTORS.DISCUSSION_COUNT);
            if (countNode !== null) {
                // Increment the count and update display.
                countNode.setData('count', parseInt(countNode.getData('count'), 10) + 1);
                countNode.setHTML(M.util.get_string('xdiscussions', 'mod_hsuforum', countNode.getData('count')));
            }
        },

        /**
         * Delete post and update view
         *
         * @method handlePostDelete
         * @param e
         */
        handlePostDelete: function(e) {
            var node = Y.one(SELECTORS.POST_BY_ID.replace('%d', e.postid));
            if (node === null) {
                return;
            }

            this.get('io').send({
                postid: e.postid,
                sesskey: M.cfg.sesskey,
                action: 'delete_post'
            }, function(data) {
                if (node.hasAttribute('data-isdiscussion')) {
                    // Redirect for now because discussions need to be re-rendered due to navigation.
                    window.location.href = data.redirecturl;
                } else {
                    var discNode = Y.one(SELECTORS.DISCUSSION_BY_ID.replace('%d', node.getData('discussionid')));
                    discNode.replace(data.html);
                    this.fire(EVENTS.POST_DELETED, data);
                }
            }, this);
        },

        /**
         * Load more discussions onto the page
         *
         * @param {Integer} page
         * @param {Integer} perpage
         * @param {Function} fn
         * @param context
         */
        loadMoreDiscussions: function(page, perpage, fn, context) {
            var node = Y.one(SELECTORS.LOAD_TARGET);

            if (!(node instanceof Y.Node)) {
                return;
            }

            this.get('io').send({
                page: page,
                perpage: perpage,
                action: 'discussions_html'
            }, function(data) {
                node.insert(data.html, 'before');
                fn.call(context);
            }, this);
        }
    }
);

M.mod_hsuforum.Dom = DOM;
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
     * Init
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
     * @param {Object} req
     */
    discussion: function(req) {
        this.get('article').viewDiscussion(req.query.d, req.query.postid);
    },

    /**
     * Post editing
     *
     * @param {Object} req
     */
    post: function(req) {
        if (!Y.Lang.isUndefined(req.query.reply)) {
            this.get('article').get('form').showReplyToForm(req.query.reply);
        } else if (!Y.Lang.isUndefined(req.query.forum)) {
            this.get('article').get('form').showAddDiscussionForm(req.query.forum);
        } else if (!Y.Lang.isUndefined(req.query['delete'])) {
            this.get('article').confirmDelete(req.query['delete']);
        } else if (!Y.Lang.isUndefined(req.query.edit) || !Y.Lang.isUndefined(req.query.prune)) {
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
     * @method routeUrl
     * @param {String} url
     * @returns {boolean}
     */
    routeUrl: function(url) {
        var path = this.removeRoot(url);

        if (this.hasRoute(path)) {
            this.save(path);
            return true;
        }
        return false;
    },

    /**
     * Add discussion button handler
     *
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
        article: { value: null },

        root: {
            value: '/mod/hsuforum'
        },

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
/**
 * Form Handler
 *
 * @module moodle-mod_hsuforum-form
 */

/**
 * Handles the display and processing of several forms, including:
 *  Adding a reply
 *  Adding a discussion
 *
 * @constructor
 * @namespace M.mod_hsuforum
 * @class Form
 * @extends Y.Base
 */
function FORM() {
    FORM.superclass.constructor.apply(this, arguments);
}

FORM.NAME = 'moodle-mod_hsuforum-form';

FORM.ATTRS = {
    /**
     * Used for requests
     *
     * @attribute io
     * @type M.mod_hsuforum.Io
     * @required
     */
    io: { value: null }
};

Y.extend(FORM, Y.Base,
    {
        /**
         * Displays the reply form for a discussion
         * or for a post.
         *
         * @param parentNode
         * @private
         */
        _displayReplyForm: function(parentNode) {
            var template = Y.one(SELECTORS.REPLY_TEMPLATE).getHTML();
            var wrapperNode = parentNode.one(SELECTORS.FORM_REPLY_WRAPPER);

            if (wrapperNode instanceof Y.Node) {
                wrapperNode.replace(template);
            } else {
                parentNode.append(template);
            }
            wrapperNode = parentNode.one(SELECTORS.FORM_REPLY_WRAPPER);

            this.attachFormWarnings();

            // Update form to reply to our post.
            wrapperNode.one(SELECTORS.INPUT_REPLY).setAttribute('value', parentNode.getData('postid'));

            var advNode = wrapperNode.one(SELECTORS.FORM_ADVANCED);
            advNode.setAttribute('href', advNode.getAttribute('href').replace(/reply=\d+/, 'reply=' + parentNode.getData('postid')));

            if (parentNode.hasAttribute('data-ispost')) {
                wrapperNode.one('legend').setHTML(
                    M.util.get_string('replytox', 'mod_hsuforum', parentNode.getData('author'))
                );
            }
        },

        /**
         * Submits a form and handles errors.
         *
         * @param wrapperNode
         * @param {Function} fn
         * @private
         */
        _submitReplyForm: function(wrapperNode, fn) {
            wrapperNode.all('button').setAttribute('disabled', 'disabled');
            this.get('io').submitForm(wrapperNode.one('form'), function(data) {
                if (data.errors === true) {
                    wrapperNode.one(SELECTORS.VALIDATION_ERRORS).setHTML(data.html).addClass('notifyproblem');
                    wrapperNode.all('button').removeAttribute('disabled');
                } else {
                    fn.call(this, data);
                }
            }, this, true);
        },

        /**
         * All of our forms need to warn the user about
         * navigating away when they have changes made
         * to the form.  This ensures all forms have
         * this feature enabled.
         */
        attachFormWarnings: function() {
            Y.all(SELECTORS.ALL_FORMS).each(function(formNode) {
                if (!formNode.hasClass('form-checker-added')) {
                    M.core_formchangechecker.init({ formid: formNode.generateID() });
                    formNode.addClass('form-checker-added');
                }
            });
        },

        /**
         * Removes all dynamically opened forms.
         */
        removeAllForms: function() {

            Y.all(SELECTORS.POSTS + ' ' + SELECTORS.FORM_REPLY).remove(true);

            var node = Y.one(SELECTORS.ADD_DISCUSSION_TARGET);
            if (node !== null) {
                node.empty();
            }
        },

        /**
         * Show a reply form for a given post
         *
         * @param postId
         */
        showReplyToForm: function(postId) {
            var postNode = Y.one(SELECTORS.POST_BY_ID.replace('%d', postId));

            if (postNode.hasAttribute('data-ispost')) {
                this._displayReplyForm(postNode);
            }
            postNode.one(SELECTORS.INPUT_MESSAGE).focus();
        },

        /**
         * Handler for when the reply form is submitted
         *
         * @param e
         */
        handleSubmitReplyTo: function(e) {

            e.preventDefault();

            var parentNode  = e.currentTarget.ancestor(SELECTORS.POST_TARGET),
                wrapperNode = parentNode.one(SELECTORS.FORM_REPLY_WRAPPER);

            this._submitReplyForm(wrapperNode, function(data) {
                this.fire(EVENTS.POST_CREATED, data);

                if (parentNode.hasAttribute('data-isdiscussion')) {
                    // This actually re-displays it.  AKA Reset.
                    this._displayReplyForm(parentNode);
                }
            });
        },

        /**
         * Show the add discussion form
         */
        showAddDiscussionForm: function() {
            Y.one(SELECTORS.ADD_DISCUSSION_TARGET)
                .setHTML(Y.one(SELECTORS.DISCUSSION_TEMPLATE).getHTML())
                .one(SELECTORS.INPUT_SUBJECT)
                .focus();

            this.attachFormWarnings();
        },

        /**
         * Handle add discussion submit
         *
         * @param e
         */
        handleSubmitAddDiscussion: function(e) {

            e.preventDefault();

            var wrapperNode = e.currentTarget.ancestor(SELECTORS.FORM_REPLY_WRAPPER);

            this._submitReplyForm(wrapperNode, function(data) {
                this.removeAllForms();
                this.fire(EVENTS.DISCUSSION_CREATED, data);
            });
        }
    }
);

M.mod_hsuforum.Form = FORM;
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
                return;
            }
            var rootNode = Y.one(SELECTORS.CONTAINER);
            if (rootNode === null) {
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
            form.on(EVENTS.DISCUSSION_CREATED, router.handleViewDiscussion, router);

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
                return;
            }

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


}, '@VERSION@', {
    "requires": [
        "base",
        "node",
        "event",
        "router",
        "yui2-menu",
        "core_rating",
        "querystring",
        "moodle-mod_hsuforum-io",
        "moodle-mod_hsuforum-livelog",
        "moodle-core-formchangechecker"
    ]
});
