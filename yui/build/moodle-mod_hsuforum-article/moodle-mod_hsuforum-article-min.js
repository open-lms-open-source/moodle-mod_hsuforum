YUI.add("moodle-mod_hsuforum-article",function(e,t){function s(){s.superclass.constructor.apply(this,arguments)}function u(){u.superclass.constructor.apply(this,arguments)}function a(){a.superclass.constructor.apply(this,arguments)}var n={DISCUSSION_EDIT:"hsuforum-thread-edit",DISCUSSION_EXPANDED:"hsuforum-thread-article-expanded",POST_EDIT:"hsuforum-post-edit"},r={ADD_DISCUSSION:"#newdiscussionform input[type=submit]",ADD_DISCUSSION_TARGET:".hsuforum-add-discussion-target",ALL_FORMS:".hsuforum-reply-wrapper form",CONTAINER:".mod-hsuforum-posts-container",CONTAINER_LINKS:".mod-hsuforum-posts-container a",DISCUSSION:".hsuforum-thread",DISCUSSIONS:".hsuforum-threads-wrapper",DISCUSSION_EDIT:"."+n.DISCUSSION_EDIT,DISCUSSION_BY_ID:'.hsuforum-thread[data-discussionid="%d"]',DISCUSSION_COUNT:".hsuforum-discussion-count",DISCUSSION_TARGET:".hsuforum-new-discussion-target",DISCUSSION_TEMPLATE:"#hsuforum-discussion-template",DISCUSSION_VIEW:".hsuforum-thread-view",EDITABLE_MESSAGE:"[contenteditable]",FORM:".hsuforum-form",FORM_ADVANCED:".hsuforum-use-advanced",FORM_REPLY_WRAPPER:".hsuforum-reply-wrapper",INPUT_FORUM:'input[name="forum"]',INPUT_MESSAGE:'textarea[name="message"]',INPUT_REPLY:'input[name="reply"]',INPUT_SUBJECT:'input[name="subject"]',LINK_CANCEL:".hsuforum-cancel",NO_DISCUSSIONS:".forumnodiscuss",NOTIFICATION:".hsuforum-notification",OPTIONS_TO_PROCESS:".hsuforum-options-menu.unprocessed",PLACEHOLDER:".thread-replies-placeholder",POSTS:".hsuforum-thread-replies",POST_BY_ID:'.hsuforum-post-target[data-postid="%d"]',POST_EDIT:"."+n.POST_EDIT,POST_TARGET:".hsuforum-post-target",RATE:".forum-post-rating",RATE_POPUP:".forum-post-rating a",REPLY_TEMPLATE:"#hsuforum-reply-template",SEARCH_PAGE:"#page-mod-hsuforum-search",VALIDATION_ERRORS:".hsuforum-validation-errors",VIEW_POSTS:".hsuforum-view-posts"},i={DISCUSSION_CREATED:"discussion:created",DISCUSSION_DELETED:"discussion:deleted",FORM_CANCELED:"form:canceled",POST_CREATED:"post:created",POST_DELETED:"post:deleted",POST_UPDATED:"post:updated"};M.mod_hsuforum=M.mod_hsuforum||{},s.NAME="moodle-mod_hsuforum-dom",s.ATTRS={io:{value:null}},e.extend(s,e.Base,{initializer:function(){e.all(r.RATE).addClass("processed"),this.initOptionMenus()},initFeatures:function(){this.initOptionMenus(),this.initRatings()},initRatings:function(){e.all(r.RATE).each(function(t){if(t.hasClass("processed"))return;M.core_rating.Y=e,t.all("select.postratingmenu").each(M.core_rating.attach_rating_events,M.core_rating),t.all("input.postratingmenusubmit").setStyle("display","none"),t.addClass("processed")})},initOptionMenus:function(){e.all(r.OPTIONS_TO_PROCESS).each(function(t){t.removeClass("unprocessed");var n=new e.YUI2.widget.Menu(t.generateID(),{lazyLoad:!0});n.render(e.one(r.CONTAINER).generateID()),e.one("#"+t.getData("controller")).on("click",function(e){e.preventDefault(),n.cfg.setProperty("y",e.currentTarget.getY()+e.currentTarget.get("offsetHeight")),n.cfg.setProperty("x",e.currentTarget.getX()),n.show()})})},handleViewRating:function(e){if(e.currentTarget.ancestor(".helplink")!==null)return;e.preventDefault(),openpopup(e,{url:e.currentTarget.get("href")+"&popup=1",name:"ratings",options:"height=400,width=600,top=0,left=0,menubar=0,location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent"})},markPostAsRead:function(e,t,n){this.get("io").send({postid:e,action:"markread"},t,n)},incrementDiscussionCount:function(t){var n=e.one(r.DISCUSSION_COUNT);n!==null&&(n.setData("count",parseInt(n.getData("count"),10)+t),n.setHTML(M.util.get_string("xdiscussions","mod_hsuforum",n.getData("count"))))},displayNotification:function(t){var n=e.Node.create(t);e.one(r.NOTIFICATION).append(n),setTimeout(function(){n.remove(!0)},1e4)},handleNotification:function(t){e.Lang.isString(t.notificationhtml)&&t.notificationhtml.trim().length>0&&this.displayNotification(t.notificationhtml)},handleUpdateDiscussion:function(t){var n=e.one("#discussionsview");n?n.setHTML(t.html):(n=e.one(r.DISCUSSION_BY_ID.replace("%d",t.discussionid)),n?n.replace(t.html):e.one(r.DISCUSSION_TARGET).insert(t.html,"after"))},handleDiscussionCreated:function(){e.one(r.NO_DISCUSSIONS)&&e.one(r.NO_DISCUSSIONS).remove()},handleDiscussionDeleted:function(t){var n=e.one(r.POST_BY_ID.replace("%d",t.postid));if(n===null||!n.hasAttribute("data-isdiscussion"))return;e.one(r.DISCUSSIONS)?(n.remove(!0),this.incrementDiscussionCount(-1),e.one(r.DISCUSSION_COUNT).focus()):window.location.href=t.redirecturl}}),M.mod_hsuforum.Dom=s;var o=e.Base.create("hsuforumRouter",e.Router,[],{initializer:function(){},discussion:function(e){this.get("article").viewDiscussion(e.query.d,e.query.postid)},post:function(t){e.Lang.isUndefined(t.query.reply)?e.Lang.isUndefined(t.query.forum)?e.Lang.isUndefined(t.query["delete"])?e.Lang.isUndefined(t.query.edit)?e.Lang.isUndefined(t.query.prune)||(window.location.href=t.url):this.get("article").get("form").showEditForm(t.query.edit):this.get("article").confirmDeletePost(t.query["delete"]):this.get("article").get("form").showAddDiscussionForm(t.query.forum):this.get("article").get("form").showReplyToForm(t.query.reply)},focusHash:function(t){var n=t.get("href").split("#");setTimeout(function(){e.one("#"+n[1]).ancestor("li").focus()},300)},handleRoute:function(e){if(e.button!==1||e.ctrlKey||e.metaKey||e.currentTarget.hasClass("disable-router")||e.currentTarget.hasClass("autolink")||e.currentTarget.ancestor(".posting")){e.currentTarget.get("href").indexOf("#")>-1&&this.focusHash(e.currentTarget);return}M.mod_hsuforum.restoreEditor(),this.routeUrl(e.currentTarget.get("href"))&&e.preventDefault()},routeUrl:function(e){return this.hasRoute(e)?(this.save(this.removeRoot(e)),!0):!1},handleAddDiscussionRoute:function(e){e.preventDefault(),M.mod_hsuforum.restoreEditor();if(typeof e.currentTarget=="undefined")return;var t=e.currentTarget.ancestor("form"),n=t.one(r.INPUT_FORUM).get("value");this.save(t.get("action")+"?forum="+n)},handleViewDiscussion:function(t){var n="/discuss.php?d="+
t.discussionid;e.Lang.isUndefined(t.postid)||(n=n+"&postid="+t.postid),this.save(n)},hideForms:function(e,t,n){this.get("article").get("form").restoreDateFields(),this.get("article").get("form").removeAllForms(),n()}},{ATTRS:{article:{value:null},root:{valueFn:function(){return M.cfg.wwwroot.replace(this._regexUrlOrigin,"")+"/mod/hsuforum"}},routes:{value:[{path:"/view.php",callbacks:["hideForms"]},{path:"/discuss.php",callbacks:["hideForms","discussion"]},{path:"/post.php",callbacks:["hideForms","post"]}]}}});M.mod_hsuforum.Router=o,u.NAME="moodle-mod_hsuforum-form",u.ATTRS={io:{value:null}},e.extend(u,e.Base,{handleFormPaste:function(e){var t="",n=window.getSelection(),r=function(e){var t=document.createElement("div");t.innerHTML=e,tags=t.getElementsByTagName("*");for(var n=0,r=tags.length;n<r;n++)tags[n].removeAttribute("id"),tags[n].removeAttribute("style"),tags[n].removeAttribute("size"),tags[n].removeAttribute("color"),tags[n].removeAttribute("bgcolor"),tags[n].removeAttribute("face"),tags[n].removeAttribute("align");return t.innerHTML},i=!1;e._event&&e._event.clipboardData&&e._event.clipboardData.getData?i=e._event.clipboardData:window.clipboardData&&window.clipboardData.getData&&(i=window.clipboardData);if(i){if(i.types){if(/text\/html/.test(i.types)||i.types.contains("text/html"))t=i.getData("text/html");else if(/text\/plain/.test(i.types)||i.types.contains("text/plain"))t=i.getData("text/plain")}else t=i.getData("Text");if(t!==""){if(n.getRangeAt&&n.rangeCount){var s=n.getRangeAt(0),o=document.createElement("p");o.innerHTML=r(t),o.childNodes[0].tagName==="META"&&o.removeChild(o.childNodes[0]);var u=o.childNodes[o.childNodes.length-1];for(var a=0;a<=o.childNodes.length;a++){var f=o.childNodes[o.childNodes.length-1];s.insertNode(f)}s.setStartAfter(u),s.setEndAfter(u),n.removeAllRanges(),n.addRange(s)}return e._event.preventDefault&&(e._event.stopPropagation(),e._event.preventDefault()),!1}}setTimeout(function(){var t=r(e.currentTarget.get("innerHTML"));e.currentTarget.setContent(t);var n=document.createRange(),i=window.getSelection(),s=function(e){var t=e.childNodes;if(!t)return!1;var n=t[t.length-1];if(!n||typeof n=="undefined")return e;var r=s(n);return r&&typeof r!="undefined"?r:n&&typeof n!="undefined"?n:e},o=s(e.currentTarget._node),u=1;typeof o.innerHTML!="undefined"?u=o.innerHTML.length:u=o.length,n.setStart(o,u),n.collapse(!0),i.removeAllRanges(),i.addRange(n)},100)},handleTimeToggle:function(e){e.currentTarget.get("checked")?e.currentTarget.ancestor(".fdate_selector").all("select").set("disabled",""):e.currentTarget.ancestor(".fdate_selector").all("select").set("disabled","disabled")},_displayReplyForm:function(t){var n=e.one(r.REPLY_TEMPLATE).getHTML(),i=t.one(r.FORM_REPLY_WRAPPER);i instanceof e.Node?i.replace(n):t.append(n),i=t.one(r.FORM_REPLY_WRAPPER),this.attachFormWarnings(),i.one(r.INPUT_REPLY).setAttribute("value",t.getData("postid"));var s=i.one(r.FORM_ADVANCED);s.setAttribute("href",s.getAttribute("href").replace(/reply=\d+/,"reply="+t.getData("postid"))),t.hasAttribute("data-ispost")&&i.one("legend").setHTML(M.util.get_string("replytox","mod_hsuforum",t.getData("author")))},_copyMessage:function(e){var t=e.one(r.EDITABLE_MESSAGE).get("innerHTML");e.one(r.INPUT_MESSAGE).set("value",t)},_submitReplyForm:function(e,t){e.all("button").setAttribute("disabled","disabled"),this._copyMessage(e);var n=e.all("form input[type=file]");this.get("io").submitForm(e.one("form"),function(n){n.yuiformsubmit=1,n.errors===!0?(e.one(r.VALIDATION_ERRORS).setHTML(n.html).addClass("notifyproblem"),e.all("button").removeAttribute("disabled")):t.call(this,n)},this,n._nodes.length>0)},attachFormWarnings:function(){e.all(r.ALL_FORMS).each(function(e){if(!e.hasClass("form-checker-added")){var t=M.core_formchangechecker.init({formid:e.generateID()});e.addClass("form-checker-added"),e.one(r.EDITABLE_MESSAGE).on("keypress",M.core_formchangechecker.set_form_changed,t)}})},removeAllForms:function(){e.all(r.POSTS+" "+r.FORM_REPLY_WRAPPER).each(function(e){!e.ancestor(r.DISCUSSION_EDIT)&&!e.ancestor(r.POST_EDIT)&&e.remove(!0)});var t=e.one(r.ADD_DISCUSSION_TARGET);t!==null&&t.empty()},handleCancelForm:function(e){e.preventDefault(),this.restoreDateFields(),M.mod_hsuforum.restoreEditor();var t=e.target.ancestor(r.POST_TARGET);if(!t){t=e.target.ancestor(r.ADD_DISCUSSION_TARGET),e.target.ancestor(r.FORM_REPLY_WRAPPER).remove(!0);if(t)return;return}t.removeClass(n.POST_EDIT).removeClass(n.DISCUSSION_EDIT),e.target.ancestor(r.FORM_REPLY_WRAPPER).remove(!0),this.fire(i.FORM_CANCELED,{discussionid:t.getData("discussionid"),postid:t.getData("postid")})},handleFormSubmit:function(e){e.preventDefault(),M.mod_hsuforum.restoreEditor();var t=e.currentTarget.ancestor(r.FORM_REPLY_WRAPPER);this._submitReplyForm(t,function(e){this.restoreDateFields();switch(e.eventaction){case"postupdated":this.fire(i.POST_UPDATED,e);break;case"postcreated":this.fire(i.POST_UPDATED,e);break;case"discussioncreated":this.fire(i.DISCUSSION_CREATED,e)}})},showReplyToForm:function(t){var n=e.one(r.POST_BY_ID.replace("%d",t));n.hasAttribute("data-ispost")&&this._displayReplyForm(n),n.one(r.EDITABLE_MESSAGE).focus()},setDateField:function(t,n,r){var i=new Date(r*1e3),s=i.getDate(),o=i.getMonth()+1,u=i.getFullYear();n?e.one("#id_time"+t+"_enabled").set("checked","checked"):e.one("#id_time"+t+"_enabled").removeAttribute("checked"),e.one("#id_time"+t+"_day").set("value",s),e.one("#id_time"+t+"_month").set("value",o),e.one("#id_time"+t+"_year").set("value",u),this.setDateFieldsClassState()},resetDateField:function(t){if(!e.one("#discussion_dateform fieldset"))return;var n=Math.floor(Date.now()/1e3);this.setDateField(t,!1,n)},resetDateFields:function(){var e=["start","end"];for(var t in e)this.resetDateField(e[t])},setDateFieldsClassState:function(){var t=e.one("fieldset.dateform_fieldset");if(!t)return;t.all(".fdate_selector").each(function(e){e.one("input").get("checked")?e.all("select").set("disabled",""):e.all("select").set("disabled","disabled"
)})},applyDateFields:function(){var t=e.one("#discussion_dateform fieldset");if(!t)return;t.addClass("dateform_fieldset"),t.removeClass("hidden"),t.one("legend")&&t.one("legend").remove(),e.one(".dateformtarget").append(t),e.all(".dateformtarget .fitem_fdate_selector a").addClass("disable-router"),this.setDateFieldsClassState()},setDateFields:function(e,t){e==0?this.resetDateField("start"):this.setDateField("start",!0,e),t==0?this.resetDateField("end"):this.setDateField("end",!0,t)},restoreDateFields:function(){e.one("#discussion_dateform")&&e.one("#discussion_dateform").append(e.one(".dateform_fieldset"))},showAddDiscussionForm:function(){e.one(r.ADD_DISCUSSION_TARGET).setHTML(e.one(r.DISCUSSION_TEMPLATE).getHTML()).one(r.INPUT_SUBJECT).focus(),this.resetDateFields(),this.applyDateFields(),this.attachFormWarnings()},showEditForm:function(t){var i=e.one(r.POST_BY_ID.replace("%d",t));if(i.hasClass(n.DISCUSSION_EDIT)||i.hasClass(n.POST_EDIT)){i.one(r.EDITABLE_MESSAGE).focus();return}var s=this;this.get("io").send({discussionid:i.getData("discussionid"),postid:i.getData("postid"),action:"edit_post_form"},function(e){i.prepend(e.html),i.hasAttribute("data-isdiscussion")?i.addClass(n.DISCUSSION_EDIT):i.addClass(n.POST_EDIT),i.one(r.EDITABLE_MESSAGE).focus(),e.isdiscussion&&(s.applyDateFields(),s.setDateFields(e.timestart,e.timeend)),this.attachFormWarnings()},this)}}),M.mod_hsuforum.Form=u,a.NAME=t,a.ATTRS={contextId:{value:undefined},io:{readOnly:!0},dom:{readOnly:!0},router:{readOnly:!0},form:{readOnly:!0},liveLog:{readOnly:!0},editorMutateObserver:null,currentEditLink:null},e.extend(a,e.Base,{initializer:function(){this._set("router",new M.mod_hsuforum.Router({article:this,html5:!1})),this._set("io",new M.mod_hsuforum.Io({contextId:this.get("contextId")})),this._set("dom",new M.mod_hsuforum.Dom({io:this.get("io")})),this._set("form",new M.mod_hsuforum.Form({io:this.get("io")})),this._set("liveLog",M.mod_hsuforum.init_livelog()),this.bind()},bind:function(){var t=document.getElementsByClassName("hsuforum-post-unread")[0];if(t&&location.hash==="#unread"){var n=document.getElementById(t.id).parentNode;if(navigator.userAgent.match(/Trident|MSIE/)){var s,o;s=n.offsetTop,o=n;while(o=o.offsetParent)s+=o.offsetTop;window.scrollTo(0,s)}else n.scrollIntoView();n.focus()}if(e.one(r.SEARCH_PAGE)!==null)return;var u=this.get("dom"),a=this.get("form"),f=this.get("router");e.delegate("paste",a.handleFormPaste,document,".hsuforum-textarea",a);var l=".hsuforum-discussion .fdate_selector input";e.delegate("click",a.handleTimeToggle,document,l,a),e.delegate("click",a.handleCancelForm,document,r.LINK_CANCEL,a),e.delegate("click",f.handleRoute,document,r.CONTAINER_LINKS,f),e.delegate("click",u.handleViewRating,document,r.RATE_POPUP,u),e.delegate("click",function(t){var n=e.one("#hiddenadvancededitorcont"),r,i,s=this,o;if(!n)return;t.preventDefault(),i=e.one("#hiddenadvancededitoreditable"),r=i.ancestor(".editor_atto"),r?M.mod_hsuforum.toggleAdvancedEditor(s):(s.setContent(M.util.get_string("loadingeditor","hsuforum")),o=setInterval(function(){r=i.ancestor(".editor_atto"),r&&(clearInterval(o),M.mod_hsuforum.toggleAdvancedEditor(s))},500))},document,".hsuforum-use-advanced"),e.delegate("submit",a.handleFormSubmit,document,r.FORM,a),e.delegate("click",f.handleAddDiscussionRoute,document,r.ADD_DISCUSSION,f),a.on(i.POST_CREATED,u.handleUpdateDiscussion,u),a.on(i.POST_CREATED,u.handleNotification,u),a.on(i.POST_CREATED,f.handleViewDiscussion,f),a.on(i.POST_CREATED,this.handleLiveLog,this),a.on(i.POST_UPDATED,this.handlePostUpdated,this),a.on(i.DISCUSSION_CREATED,u.handleUpdateDiscussion,u),a.on(i.DISCUSSION_CREATED,u.handleDiscussionCreated,u),a.on(i.DISCUSSION_CREATED,u.handleNotification,u),a.on(i.DISCUSSION_CREATED,f.handleViewDiscussion,f),a.on(i.DISCUSSION_CREATED,this.handleLiveLog,this),this.on(i.DISCUSSION_DELETED,u.handleDiscussionDeleted,u),this.on(i.DISCUSSION_DELETED,u.handleNotification,u),this.on(i.DISCUSSION_DELETED,this.handleLiveLog,this),this.on(i.POST_DELETED,u.handleUpdateDiscussion,u),this.on(i.POST_DELETED,f.handleViewDiscussion,f),this.on(i.POST_DELETED,u.handleNotification,u),this.on(i.POST_DELETED,this.handleLiveLog,this),a.on(i.FORM_CANCELED,f.handleViewDiscussion,f)},handlePostUpdated:function(e){var t=this.get("dom"),n=this.get("form"),r=this.get("router");n.restoreDateFields(),t.handleUpdateDiscussion(e),r.handleViewDiscussion(e),t.handleNotification(e),this.handleLiveLog(e)},handleLiveLog:function(t){e.Lang.isString(t.livelog)&&this.get("liveLog").logText(t.livelog)},viewDiscussion:function(t,n){var i=e.one(r.DISCUSSION_BY_ID.replace("%d",t));if(!(i instanceof e.Node))return;if(!e.Lang.isUndefined(n)){var s=e.one(r.POST_BY_ID.replace("%d",n));s===null||s.hasAttribute("data-isdiscussion")?i.focus():s.get("parentNode").focus()}else i.focus()},confirmDeletePost:function(t){var n=e.one(r.POST_BY_ID.replace("%d",t));if(n===null)return;window.confirm(M.str.mod_hsuforum.deletesure)===!0&&this.deletePost(t)},deletePost:function(t){var n=e.one(r.POST_BY_ID.replace("%d",t));if(n===null)return;this.get("io").send({postid:t,sesskey:M.cfg.sesskey,action:"delete_post"},function(e){n.hasAttribute("data-isdiscussion")?this.fire(i.DISCUSSION_DELETED,e):this.fire(i.POST_DELETED,e)},this)}}),M.mod_hsuforum.Article=a,M.mod_hsuforum.init_article=function(e){new a(e)},M.mod_hsuforum.dispatchClick=function(e){if(document.createEvent){var t=new MouseEvent("click",{view:window,bubbles:!0,cancelable:!0});e.dispatchEvent(t)}else e.fireEvent&&e.fireEvent("onclick")},M.mod_hsuforum.restoreEditor=function(){var t=e.one("#hiddenadvancededitorcont");if(t){var n=e.one("#hiddenadvancededitoreditable");if(!n)return;var r=n.ancestor(".editor_atto"),i=M.mod_hsuforum.Article.currentEditLink,s=!1;i&&(s=i.previous(".hsuforum-textarea"));var o=!r||r.getComputedStyle("display")==="none";o||(r.one(".atto_html_button.highlight")&&M.mod_hsuforum.dispatchClick(r.one(".atto_html_button.highlight")._node),s&&s.setContent(n.getContent(
))),M.mod_hsuforum.toggleAdvancedEditor(!1,!0),e.one("#hiddenadvancededitorcont").show(),e.one("#hiddenadvancededitorcont")._node.style.display="block",t.appendChild(r),t.appendChild(e.one("#hiddenadvancededitor"))}},M.mod_hsuforum.toggleAdvancedEditor=function(t,n,r){var i=!1;n||(i=t&&t.getAttribute("aria-pressed")==="false"),t&&(M.mod_hsuforum.Article.currentEditLink=t,i?t.removeClass("hideadvancededitor"):t.addClass("hideadvancededitor"));if(n){if(!t){var s=e.all(".hsuforum-use-advanced");for(var o=0;o<s.size();o++){var u=s.item(o);if(r&&r===u)continue;M.mod_hsuforum.toggleAdvancedEditor(u,!0)}return}}else M.mod_hsuforum.toggleAdvancedEditor(!1,!0,t);var a=e.one("#hiddenadvancededitorcont"),f,l=t.previous(".hsuforum-textarea"),c;if(!a)throw"Failed to get editor";f=e.one("#hiddenadvancededitoreditable"),c=f.ancestor(".editor_atto"),l&&f.setStyle("height",l.getDOMNode().offsetHeight+"px");var h=!1;if(!c||c.getComputedStyle("display")==="none")h=!0;if(i){t.setAttribute("aria-pressed","true"),t.setContent(M.util.get_string("hideadvancededitor","hsuforum")),l.hide(),c.one(".atto_html_button.highlight")&&e.one("#hiddenadvancededitor").show(),c.show(),l.insert(c,"before"),l.insert(e.one("#hiddenadvancededitor"),"before");var p=e.one("#hiddenadvancededitordraftid").cloneNode();p.id="hiddenadvancededitordraftidclone",l.insert(p,"before"),f.setContent(l.getContent()),f.focus();var d=function(){l.setContent(f.getContent())};window.MutationObserver?(M.mod_hsuforum.Article.editorMutateObserver=new MutationObserver(d),M.mod_hsuforum.Article.editorMutateObserver.observe(f.getDOMNode(),{childList:!0,characterData:!0,subtree:!0})):f.getDOMNode().addEventListener("DOMCharacterDataModified",editAreachanged,!1)}else t.setAttribute("aria-pressed","false"),M.mod_hsuforum.Article.editorMutateObserver&&M.mod_hsuforum.Article.editorMutateObserver.disconnect(),t.setContent(M.util.get_string("useadvancededitor","hsuforum")),l.show(),h||(c.one(".atto_html_button.highlight")&&M.mod_hsuforum.dispatchClick(c.one(".atto_html_button.highlight")._node),l.setContent(f.getContent())),e.one("#hiddenadvancededitor").hide(),c.hide()}},"@VERSION@",{requires:["base","node","event","router","core_rating","querystring","moodle-mod_hsuforum-io","moodle-mod_hsuforum-livelog","moodle-core-formchangechecker"]});
