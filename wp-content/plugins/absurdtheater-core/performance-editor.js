/* global wp */
(function () {
    'use strict';
    if (!window.wp || !wp.plugins || !wp.editPost) return;
    var el=wp.element.createElement;
    var useSelect=wp.data.useSelect;
    var dispatch=wp.data.dispatch;
    function CorePerformancePanel() {
        var meta=useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);
        function change(key, value) {
            var o={}; o[key]=value;
            dispatch('core/editor').editPost({meta:o});
        }
        function text(key, label, help) {
            return el(wp.components.TextControl,{key:key,label:label,help:help||'',
                value:meta[key]||'',onChange:function(v){change(key,v);}});
        }
        var select=function(key,label,items) {
            return el(wp.components.SelectControl,{key:key,label:label,
                value:meta[key]||'',options:items.map(function(s){return {value:s,label:s||'— Not specified —'};}),
                onChange:function(v){change(key,v);}});
        };
        return el(wp.editPost.PluginDocumentSettingPanel,
            {name:'absurd-performance-fields',title:'ABSURD Performance Details'},
            [
                select('age_mark','Age mark (only confirmed)', ['', '0+','6+','12+','16+','18+']),
                el(wp.components.TextControl,{key:'duration',label:'Duration (minutes, 1–600)',type:'number',min:0,max:600,
                    value:meta.duration_minutes||0,onChange:function(v){change('duration_minutes',Number(v)||0);}}),
                select('interval_info','Interval', ['', 'with_interval','without_interval']),
                el(wp.components.TextareaControl,{key:'cast_text',label:'Confirmed cast only',value:meta.cast_text||'',
                    onChange:function(v){change('cast_text',v);}}),
                text('seo_title_override','SEO title override (optional)'),
                text('seo_description_override','SEO description override (optional)')
            ]
        );
    }
    wp.plugins.registerPlugin('absurd-performance-editor',{render:CorePerformancePanel});
}());
