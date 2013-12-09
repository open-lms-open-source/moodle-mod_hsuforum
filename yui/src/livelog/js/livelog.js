/**
 * Lots of good information about what this does:
 *
 * https://developer.mozilla.org/en-US/docs/Accessibility/ARIA/ARIA_Live_Regions
 * http://oaa-accessibility.org/example/23/
 *
 * Basically this widget creates a hidden (by default) log that is
 * read to screen readers.  Behavior of the reading can be changed
 * by changing the attributes.
 *
 * @module moodle-mod_hsuforum-livelog
 */
var BOX = 'contentBox',
    LOG_BOX_TEMPLATE = '<div></div>';

/**
 * Live log
 *
 * @constructor
 * @namespace M.mod_hsuforum
 * @class LiveLog
 * @extends Y.Widget
 */
function LIVE_LOG() {
    LIVE_LOG.superclass.constructor.apply(this, arguments);
}

LIVE_LOG.NAME = NAME;

LIVE_LOG.ATTRS = {
    /**
     * This is the box that contains all of the log entries.
     *
     * @attribute logBox
     * @type Y.Node
     * @default Y.Node
     * @required
     * @readOnly
     */
    logBox: {value: Y.Node.create(LOG_BOX_TEMPLATE), readOnly: true},

    /**
     * Class names to add to the log box
     *
     * @attribute classNames
     * @type String
     * @default 'accesshide'
     * @required
     */
    classNames: {value: 'accesshide', validator: Y.Lang.isString},

    /**
     * Template to use for each log
     *
     * @attribute logTemplate
     * @type String
     * @default '<p></p>'
     * @required
     */
    logTemplate: {value: '<p></p>', validator: Y.Lang.isString},

    /**
     * When log entries are read. Possible values: off, polite and assertive
     *
     * @attribute ariaLive
     * @type String
     * @default 'polite'
     * @required
     */
    ariaLive: {value: 'polite', validator: Y.Lang.isString},

    /**
     * Which log entries are read. Possible values: additions, removals, text and all.  Can combine, EG: "additions removals"
     *
     * @attribute ariaRelevant
     * @type String
     * @default 'additions text'
     * @required
     */
    ariaRelevant: {value: 'additions text', validator: Y.Lang.isString},

    /**
     * Read the live region as a whole or not. Possible values: true or false
     *
     * @attribute ariaAtomic
     * @type String
     * @default 'false'
     * @required
     */
    ariaAtomic: {value: 'false', validator: Y.Lang.isString}
};

Y.extend(LIVE_LOG, Y.Widget,
    {
        /**
         * Render lifecycle
         *
         * @method renderUI
         */
        renderUI: function() {
            this.get(BOX).append(this.get('logBox'));
            this._updateAttributes();
        },

        /**
         * Event bind lifecycle
         *
         * @method bindUI
         */
        bindUI: function() {
            this.after([
                'ariaLiveChange',
                'ariaRelevantChange',
                'ariaAtomicChange',
                'classNamesChange'
            ], this._updateAttributes, this);
        },

        /**
         * Add some text to the log.
         *
         * @param {String} message The plain text message
         * @method log
         */
        logText: function(message) {
            Y.log('Logging message: ' + message, 'info', 'LiveLog');

            var logNode = Y.Node.create(this.get('logTemplate'));
            logNode.set('text', message);

            this.logNode(logNode);
        },

        /**
         * Add a YUI Node to the log.  Allows for complete customization of log nodes.
         * @param {Y.Node} node
         */
        logNode: function(node) {
            this.get('logBox').append(node);

            // Fire after so node is part of DOM tree.
            this.fire('logAdded', {}, node);
        },

        /**
         * Update attributes on log box
         *
         * @method _updateAttributes
         * @private
         */
        _updateAttributes: function() {
            this.get('logBox').setAttribute('role', 'log')
                .setAttribute('class', this.get('classNames'))
                .setAttribute('aria-relevant', this.get('ariaRelevant'))
                .setAttribute('aria-atomic', this.get('ariaAtomic'))
                .setAttribute('aria-live', this.get('ariaLive'));
        }
    }
);

M.mod_hsuforum = M.mod_hsuforum || {};
M.mod_hsuforum.LiveLog = LIVE_LOG;
M.mod_hsuforum.init_livelog = function(config) {
    var widget = new LIVE_LOG(config);
    widget.render();
    return widget;
};