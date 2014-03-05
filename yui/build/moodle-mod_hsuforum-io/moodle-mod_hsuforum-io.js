YUI.add('moodle-mod_hsuforum-io', function (Y, NAME) {

/**
 * Forum IO Wrapper
 *
 * @module moodle-mod_hsuforum-io
 */

var Lang = Y.Lang,
    URL_AJAX = M.cfg.wwwroot + '/mod/hsuforum/route.php';

/**
 * This provides a simple wrapper around Y.io to fetch
 * data from the server and handle any errors.
 *
 * @constructor
 * @namespace M.mod_hsuforum
 * @class Io
 * @extends Y.Base
 */
function IO() {
    IO.superclass.constructor.apply(this, arguments);
}

IO.NAME = NAME;

IO.ATTRS = {
    /**
     * Current context ID, used for AJAX requests
     *
     * @attribute contextId
     * @type Number
     * @default undefined
     * @optional
     */
    contextId: { value: undefined },
    /**
     * Used for requests
     *
     * @attribute url
     * @type String
     * @default URL_AJAX
     * @optional
     */
    url: { value: URL_AJAX, validator: Lang.isString }
};

Y.extend(IO, Y.Base,
    {
        /**
         * Internal function for handling response from IO
         *
         * @param id
         * @param response
         * @param args
         * @private
         * @method _complete
         */
        _complete: function(id, response, args) {
            var data = {};
            try {
                data = Y.JSON.parse(response.responseText);
            } catch (e) {
                alert(e.name + ": " + e.message);
                return;
            }
            if (Lang.isValue(data.error)) {
                alert(data.error);
            } else {
                args.fn.call(args.context, data);
            }
        },

        /**
         * Helper method to do a AJAX request and to do error handling
         * @method send
         * @param {Object} data
         * @param {Function} fn
         * @param {Object} context Specifies what 'this' refers to.
         * @param {String} method POST, GET, etc.  Defaults to GET
         */
        send: function(data, fn, context, method) {
            if (!Lang.isString(method)) {
                method = 'GET';
            }
            if (Lang.isUndefined(data.contextid) && !Lang.isUndefined(this.get('contextId'))) {
                data.contextid = this.get('contextId');
            }
            Y.io(this.get('url'), {
                method: method,
                context: this,
                arguments: {fn: fn, context: context },
                data: data,
                on: { complete: this._complete }
            });
        },

        /**
         * Helper method to submit a form and to do error handling
         *
         * @method submitForm
         * @param {Object} form YUI Node of the form element
         * @param {Function} fn
         * @param {Object} context Specifies what 'this' refers to.
         * @param {Boolean} uploadFiles Upload form files or not
         */
        submitForm: function(form, fn, context, uploadFiles) {
            if (!Lang.isBoolean(uploadFiles)) {
                uploadFiles = false;
            }
            var cfg = {
                method: 'POST', // Could grab from form method attr.
                context: this,
                arguments: { fn: fn, context: context },
                on: { complete: this._complete },
                form: {
                    id: form.generateID(),
                    upload: uploadFiles
                }
            };
            if (uploadFiles) {
                var url = this.get('url');
                Y.use('io-upload-iframe', function() {
                    Y.io(url, cfg);
                });
            } else {
                Y.io(this.get('url'), cfg);
            }
        }
    }
);

M.mod_hsuforum = M.mod_hsuforum || {};
M.mod_hsuforum.Io = IO;


}, '@VERSION@', {"requires": ["base", "io-base", "io-form", "io-upload-iframe", "json-parse"]});
