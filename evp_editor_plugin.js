(function() {
	tinymce.create('tinymce.plugins.wpevp', {

		init : function(ed, url) {
			var t = this;
			t.url = url;

			ed.addButton('wpevp', {
				title : 'Embed an EVP video',
				image : url + '/images/evp.png',
				cmd : 'wpEVP'
			});

			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('...');
			ed.addCommand('wpEVP', function() {
				var el = ed.selection.getNode(), post_id, vp = tinymce.DOM.getViewPort(),
					H = vp.h - 80, W = ( 640 < vp.w ) ? 640 : vp.w;

				tb_show('Embed EVP', '#TB_inline?inlineId=insert_evp&width='+W+'&height='+H);

				tinymce.DOM.setStyle( ['TB_overlay','TB_window','TB_load'], 'z-index', '999999' );
			});

			ed.onMouseDown.add(function(ed, e) {
				if ( e.target.nodeName == 'IMG' && ed.dom.hasClass(e.target, 'wpevp') ) {
					return false;
				}
			});

			ed.onBeforeSetContent.add(function(ed, o) {
				o.content = t._do_wpevp(o.content, url);
			});

			ed.onPostProcess.add(function(ed, o) {
				if (o.get)
					o.content = t._get_wpevp(o.content);
			});
		},

		_do_wpevp : function(co, url) {
			return co.replace(/\[wpevp([^\]]*)\/?\]/g, function(a,b){
				var styleattr = '';

				setwidth = b.match(/width="(.*?)"/i);
				width = '350px';
				if(setwidth) { width = setwidth[1];}
				styleattr = styleattr + 'width:'+width+';';

				setheight = b.match(/height="(.*?)"/i);
				height = '200px';
				if(setheight) { height = setheight[1];}
				styleattr = styleattr + 'height:'+height+';';

				return '<img class="mceItem wpevp-placeholder" style="'+styleattr+'" title="EVP Video Placeholder" data-settings="wpevp'+tinymce.DOM.encode(b)+'" />';
			});
		},

		_get_wpevp : function(co) {

			function getAttr(s, n) {
				n = new RegExp(n + '=[\"\']([^\"\']+)[\"\']', 'g').exec(s);
				return n ? tinymce.DOM.decode(n[1]) : '';
			}

			return co.replace(/(?:<p[^>]*>)*(<img[^>]+>)(?:<\/p>)*/g, function(a,im) {
				var cls = getAttr(im, 'class');

				if ( cls.indexOf('wpevp') != -1 ) {
					return '<p>['+tinymce.trim(getAttr(im, 'data-settings'))+']</p>';
				}

				return a;
			});
		},

		getInfo : function() {
			return {
				longname : 'Easy Video Player WordPress Plugin',
				author : 'Katz Web Services, Inc.',
				authorurl : 'http://www.katzwebservices.com',
				infourl : 'http://www.seodenver.com/easy-video-player/',
				version : "1.0"
			};
		}
	});

	tinymce.PluginManager.add('wpevp', tinymce.plugins.wpevp);
})();