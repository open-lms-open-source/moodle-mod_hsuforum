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
         * Remove crud from content on paste
         *
         *
         */
        handleFormPaste: function(e) {
            var cleanhtml = '',
            tags = '';
            setTimeout(function(){
                cleanhtml = document.createElement('p');
                cleanhtml.innerHTML = e.currentTarget.get('innerHTML');
                tags = cleanhtml.getElementsByTagName("*");
                for (var i=0, max=tags.length; i < max; i++){
                    tags[i].removeAttribute("id");
                    tags[i].removeAttribute("style");
                    tags[i].removeAttribute("size");
                    tags[i].removeAttribute("color");
                    tags[i].removeAttribute("bgcolor");
                    tags[i].removeAttribute("face");
                    tags[i].removeAttribute("align");
                }
                e.currentTarget.setContent(cleanhtml.innerHTML);
            },100);
        },

        /**
         * Displays the reply form for a discussion
         * or for a post.
         *
         * @method _displayReplyForm
         * @param parentNode
         * @private
         */
        _displayReplyForm: function(parentNode) {
            var template    = Y.one(SELECTORS.REPLY_TEMPLATE).getHTML(),
                wrapperNode = parentNode.one(SELECTORS.FORM_REPLY_WRAPPER);

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
         * Copies the content editable message into the
         * text area so it can be submitted by the form.
         *
         * @method _copyMessage
         * @param node
         * @private
         */
        _copyMessage: function(node) {
            var message = node.one(SELECTORS.EDITABLE_MESSAGE).get('innerHTML');
            node.one(SELECTORS.INPUT_MESSAGE).set('value', message);
        },

        /**
         * Submits a form and handles errors.
         *
         * @method _submitReplyForm
         * @param wrapperNode
         * @param {Function} fn
         * @private
         */
        _submitReplyForm: function(wrapperNode, fn) {
            wrapperNode.all('button').setAttribute('disabled', 'disabled');
            this._copyMessage(wrapperNode);
            this.get('io').submitForm(wrapperNode.one('form'), function(data) {
                if (data.errors === true) {
                    Y.log('Form failed to validate', 'info', 'Form');
                    wrapperNode.one(SELECTORS.VALIDATION_ERRORS).setHTML(data.html).addClass('notifyproblem');
                    wrapperNode.all('button').removeAttribute('disabled');
                } else {
                    Y.log('Form successfully submitted', 'info', 'Form');
                    fn.call(this, data);
                }
            }, this, true);
        },

        /**
         * All of our forms need to warn the user about
         * navigating away when they have changes made
         * to the form.  This ensures all forms have
         * this feature enabled.
         *
         * @method attachFormWarnings
         */
        attachFormWarnings: function() {
            Y.all(SELECTORS.ALL_FORMS).each(function(formNode) {
                if (!formNode.hasClass('form-checker-added')) {
                    var checker = M.core_formchangechecker.init({ formid: formNode.generateID() });
                    formNode.addClass('form-checker-added');

                    // On edit of content editable, trigger form change checker.
                    formNode.one(SELECTORS.EDITABLE_MESSAGE).on('keypress', M.core_formchangechecker.set_form_changed, checker);
                }
            });
        },

        /**
         * Removes all dynamically opened forms.
         *
         * @method removeAllForms
         */
        removeAllForms: function() {
            Y.log('Removing all forms', 'info', 'Form');

            Y.all(SELECTORS.POSTS + ' ' + SELECTORS.FORM_REPLY_WRAPPER).each(function(node) {
                // Don't removing forms for editing, for safety.
                if (!node.ancestor(SELECTORS.DISCUSSION_EDIT) && !node.ancestor(SELECTORS.POST_EDIT)) {
                    node.remove(true);
                }
            });

            var node = Y.one(SELECTORS.ADD_DISCUSSION_TARGET);
            if (node !== null) {
                node.empty();
            }
        },

        /**
         * A reply or edit form was canceled
         *
         * @method handleCancelForm
         * @param e
         */
        handleCancelForm: function(e) {
            e.preventDefault();

            var node = e.target.ancestor(SELECTORS.POST_TARGET);
            if (node) {
                node.removeClass(CSS.POST_EDIT)
                    .removeClass(CSS.DISCUSSION_EDIT);
            }
            e.target.ancestor(SELECTORS.FORM_REPLY_WRAPPER).remove(true);

            this.fire(EVENTS.FORM_CANCELED, {
                discussionid: node.getData('discussionid'),
                postid: node.getData('postid')
            });
        },

        /**
         * Handler for when the form is submitted
         *
         * @method handleFormSubmit
         * @param e
         */
        handleFormSubmit: function(e) {
            Y.log('Submitting edit post form', 'info', 'Form');

            e.preventDefault();

            // Put editor back to its original place in DOM.
            M.mod_hsuforum.restoreEditor();

            var wrapperNode = e.currentTarget.ancestor(SELECTORS.FORM_REPLY_WRAPPER);

            this._submitReplyForm(wrapperNode, function(data) {
                switch (data.eventaction) {
                    case 'postupdated':
                        this.fire(EVENTS.POST_UPDATED, data);
                        break;
                    case 'postcreated':
                        this.fire(EVENTS.POST_UPDATED, data);
                        break;
                    case 'discussioncreated':
                        this.fire(EVENTS.DISCUSSION_CREATED, data);
                        break;
                }
            });
        },

        /**
         * Show a reply form for a given post
         *
         * @method showReplyToForm
         * @param postId
         */
        showReplyToForm: function(postId) {
            Y.log('Show reply to post: ' + postId, 'info', 'Form');
            var postNode = Y.one(SELECTORS.POST_BY_ID.replace('%d', postId));

            if (postNode.hasAttribute('data-ispost')) {
                this._displayReplyForm(postNode);
            }
            postNode.one(SELECTORS.EDITABLE_MESSAGE).focus();
        },

        /**
         * Show the add discussion form
         *
         * @method showAddDiscussionForm
         */
        showAddDiscussionForm: function() {
            Y.log('Show discussion form', 'info', 'Form');
            Y.one(SELECTORS.ADD_DISCUSSION_TARGET)
                .setHTML(Y.one(SELECTORS.DISCUSSION_TEMPLATE).getHTML())
                .one(SELECTORS.INPUT_SUBJECT)
                .focus();

            this.attachFormWarnings();
        },

        /**
         * Display editing form for a post or discussion.
         *
         * @method showEditForm
         * @param {Integer} postId
         */
        showEditForm: function(postId) {
            var postNode = Y.one(SELECTORS.POST_BY_ID.replace('%d', postId));
            if (postNode.hasClass(CSS.DISCUSSION_EDIT) || postNode.hasClass(CSS.POST_EDIT)) {
                postNode.one(SELECTORS.EDITABLE_MESSAGE).focus();
                return;
            }
            this.get('io').send({
                discussionid: postNode.getData('discussionid'),
                postid: postNode.getData('postid'),
                action: 'edit_post_form'
            }, function(data) {
                postNode.prepend(data.html);

                if (postNode.hasAttribute('data-isdiscussion')) {
                    postNode.addClass(CSS.DISCUSSION_EDIT);
                } else {
                    postNode.addClass(CSS.POST_EDIT);
                }
                postNode.one(SELECTORS.EDITABLE_MESSAGE).focus();

                this.attachFormWarnings();
            }, this);
        }
    }
);

M.mod_hsuforum.Form = FORM;
