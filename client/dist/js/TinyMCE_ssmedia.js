!function(t){function e(i){if(n[i])return n[i].exports;var a=n[i]={i:i,l:!1,exports:{}};return t[i].call(a.exports,a,a.exports,e),a.l=!0,a.exports}var n={};e.m=t,e.c=n,e.i=function(t){return t},e.d=function(t,n,i){e.o(t,n)||Object.defineProperty(t,n,{configurable:!1,enumerable:!0,get:i})},e.n=function(t){var n=t&&t.__esModule?function(){return t.default}:function(){return t};return e.d(n,"a",n),n},e.o=function(t,e){return Object.prototype.hasOwnProperty.call(t,e)},e.p="",e(e.s=166)}({0:function(t,e){t.exports=React},1:function(t,e){t.exports=i18n},13:function(t,e){t.exports=Injector},166:function(t,e,n){"use strict";function i(t){return t&&t.__esModule?t:{default:t}}function a(t,e,n){return e in t?Object.defineProperty(t,e,{value:n,enumerable:!0,configurable:!0,writable:!0}):t[e]=n,t}var r=n(7),o=i(r),s=n(1),l=i(s),d=n(0),c=i(d),u=n(6),f=i(u),m=n(3),p=n(13),g=n(53),h=i(g),v=(0,p.provideInjector)(h.default),x='img[data-shortcode="image"]';!function(){var t={init:function(t){t.addButton("ssmedia",{icon:"image",title:"Insert Media",cmd:"ssmedia"}),t.addMenuItem("ssmedia",{icon:"image",text:"Insert Media",cmd:"ssmedia"}),t.addCommand("ssmedia",function(){(0,o.default)("#"+t.id).entwine("ss").openMediaDialog()}),t.on("BeforeExecCommand",function(e){var n=e.command,i=e.ui,a=e.value;"mceAdvImage"!==n&&"mceImage"!==n||(e.preventDefault(),t.execCommand("ssmedia",i,a))}),t.on("SaveContent",function(t){var e=(0,o.default)(t.content),n=function(t){return Object.keys(t).map(function(e){return t[e]?e+'="'+t[e]+'"':null}).filter(function(t){return null!==t}).join(" ")};e.find(x).add(e.filter(x)).each(function(){var t=(0,o.default)(this),e={src:t.attr("src"),id:t.data("id"),width:t.attr("width"),height:t.attr("height"),class:t.attr("class"),title:t.attr("title"),alt:t.attr("alt")},i="[image "+n(e)+"]";t.replaceWith(i)}),t.content="",e.each(function(){void 0!==this.outerHTML&&(t.content+=this.outerHTML)})}),t.on("BeforeSetContent",function(t){for(var e=null,n=t.content,i=/\[image(.*?)]/gi;e=i.exec(n);){var r=function(t){return t.match(/([^\s\/'"=,]+)\s*=\s*(('([^']+)')|("([^"]+)")|([^\s,\]]+))/g).reduce(function(t,e){var n=e.match(/^([^\s\/'"=,]+)\s*=\s*(?:(?:'([^']+)')|(?:"([^"]+)")|(?:[^\s,\]]+))$/),i=n[1],r=n[2]||n[3]||n[4];return Object.assign({},t,a({},i,r))},{})}(e[1]),s=(0,o.default)("<img/>").attr(Object.assign({},r,{id:void 0,"data-id":r.id,"data-shortcode":"image"})).addClass("ss-htmleditorfield-file image");n=n.replace(e[0],(0,o.default)("<div/>").append(s).html())}t.content=n})}};tinymce.PluginManager.add("ssmedia",function(e){return t.init(e)})}(),o.default.entwine("ss",function(t){t(".insert-media-react__dialog-wrapper .nav-link").entwine({onclick:function(t){return t.preventDefault()}}),t("#insert-media-react__dialog-wrapper").entwine({Element:null,Data:{},onunmatch:function(){this._clearModal()},_clearModal:function(){f.default.unmountComponentAtNode(this[0])},open:function(){this._renderModal(!0)},close:function(){this._renderModal(!1)},_renderModal:function(t){var e=this,n=function(){return e.close()},i=function(){return e._handleInsert.apply(e,arguments)},a=window.ss.store,r=window.ss.apolloClient,o=this.getOriginalAttributes();delete o.url,f.default.render(c.default.createElement(m.ApolloProvider,{store:a,client:r},c.default.createElement(v,{title:!1,show:t,onInsert:i,onHide:n,bodyClassName:"modal__dialog",className:"insert-media-react__dialog-wrapper",fileAttributes:o})),this[0])},_handleInsert:function(t,e){var n=!1;this.setData(Object.assign({},t,e));try{switch(e?e.category:"image"){case"image":n=this.insertImage();break;default:n=this.insertFile()}}catch(t){this.statusMessage(t,"bad")}return n&&this.close(),Promise.resolve()},getOriginalAttributes:function(){var e=this.getElement();if(!e)return{};var n=e.getEditor().getSelectedNode();if(!n)return{};var i=t(n),a=i.parent(".captionImage").find(".caption"),r={url:i.attr("src"),AltText:i.attr("alt"),InsertWidth:i.attr("width"),InsertHeight:i.attr("height"),TitleTooltip:i.attr("title"),Alignment:this.findPosition(i.attr("class")),Caption:a.text(),ID:i.attr("data-id")};return["InsertWidth","InsertHeight","ID"].forEach(function(t){r[t]="string"==typeof r[t]?parseInt(r[t],10):null}),r},findPosition:function(t){return["leftAlone","center","rightAlone","left","right"].find(function(e){return new RegExp("\\b"+e+"\\b").test(t)})},getAttributes:function(){var t=this.getData();return{src:t.url,alt:t.AltText,width:t.InsertWidth,height:t.InsertHeight,title:t.TitleTooltip,class:t.Alignment,"data-id":t.ID,"data-shortcode":"image"}},getExtraData:function(){var t=this.getData();return{CaptionText:t&&t.Caption}},insertFile:function(){return this.statusMessage(l.default._t("AssetAdmin.ERROR_OEMBED_REMOTE","Embed is only compatible with remote files"),"bad"),!1},insertImage:function(){var e=this.getElement();if(!e)return!1;var n=e.getEditor();if(!n)return!1;var i=t(n.getSelectedNode()),a=this.getAttributes(),r=this.getExtraData(),o=i&&i.is("img")?i:null;o&&o.parent().is(".captionImage")&&(o=o.parent());var s=i&&i.is("img")?i:t("<img />");s.attr(a).addClass("ss-htmleditorfield-file image");var l=s.parent(".captionImage"),d=l.find(".caption");r.CaptionText?(l.length||(l=t("<div></div>")),l.attr("class","captionImage "+a.class).removeAttr("data-mce-style").width(a.width),d.length||(d=t('<p class="caption"></p>').appendTo(l)),d.attr("class","caption "+a.class).text(r.CaptionText)):l=d=null;var c=l||s;return o&&o.not(c).length&&o.replaceWith(c),l&&l.prepend(s),o||(n.repaint(),n.insertContent(t("<div />").append(c).html(),{skip_undo:1})),n.addUndo(),n.repaint(),!0},statusMessage:function(e,n){var i=t("<div/>").text(e).html();t.noticeAdd({text:i,type:n,stayTime:5e3,inEffect:{left:"0",opacity:"show"}})}})})},3:function(t,e){t.exports=ReactApollo},53:function(t,e){t.exports=InsertMediaModal},6:function(t,e){t.exports=ReactDom},7:function(t,e){t.exports=jQuery}});