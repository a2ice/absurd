/* global wp, absurdCore */
(function () {
    'use strict';
    if (!window.wp || !wp.plugins || !wp.editPost || !wp.data || !wp.apiFetch) return;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useSelect = wp.data.useSelect;
    var dispatch = wp.data.dispatch;
    var Panel = wp.editPost.PluginDocumentSettingPanel;
    var C = wp.components;
    var options = window.absurdCore || { performances: [], venues: [], timezones: [] };

    function localized(utc, tz) {
        if (!utc || !tz) return '';
        try {
            var pieces = new Intl.DateTimeFormat('en-CA', {
                timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
            }).formatToParts(new Date(utc.replace(' ', 'T') + 'Z'));
            var m = {};
            pieces.forEach(function (part) { m[part.type] = part.value; });
            return m.year + '-' + m.month + '-' + m.day + 'T' + m.hour + ':' + m.minute;
        } catch (e) { return ''; }
    }
    function Select(props) {
        return el(C.SelectControl, {
            label: props.label, value: String(props.value || ''),
            options: props.options, onChange: props.onChange, help: props.help
        });
    }
    function Editor() {
        var meta = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        }, []);
        var venues = useSelect(function (select) {
            return select('core/editor').getEditedPostAttribute('venues') || [];
        }, []);
        var selectedVenue = Array.isArray(venues) && venues.length ? Number(venues[0]) : 0;
        var currentTz = meta.show_timezone || 'Europe/Moscow';
        var locState = useState(''), local = locState[0], setLocal = locState[1];
        var errorState = useState(''), error = errorState[0], setError = errorState[1];
        var workingState = useState(false), working = workingState[0], setWorking = workingState[1];
        var updated = function (key, value) {
            var values = {}; values[key] = value;
            dispatch('core/editor').editPost({ meta: Object.assign({}, meta, values) });
        };
        var links = Array.isArray(meta.ticket_links) ? meta.ticket_links : [];
        function updateLink(index, prop, value) {
            var next = links.map(function (row) { return Object.assign({}, row); });
            next[index] = Object.assign({}, next[index]);
            next[index][prop] = value;
            if (prop === 'primary' && value) next.forEach(function (row, i) { if (i !== index) row.primary = false; });
            updated('ticket_links', next);
        }
        function selectedVenueChange(v) {
            var id = Number(v || 0);
            dispatch('core/editor').editPost({ venues: id ? [id] : [] });
            var record = options.venues.find(function (x) { return Number(x.id) === id; });
            if (record && record.timezone) updated('show_timezone', record.timezone);
            setLocal('');
        }
        function convertDate() {
            setError('');
            setWorking(true);
            wp.apiFetch({
                path: '/absurd/v1/resolve-time', method: 'POST',
                data: { local: local, timezone: currentTz }
            }).then(function (response) {
                updated('show_start_utc', response.utc);
                setWorking(false);
            }).catch(function (e) {
                updated('show_start_utc', '');
                setError(e.message || 'Invalid local date/time.');
                setWorking(false);
            });
        }
        var rows = [
            el(Select, { key: 'performance', label: 'Спектакль — обязательный при публикации',
                value: meta.performance_id,
                options: [{ label: '— Select performance —', value: '' }].concat(
                    options.performances.map(function (x) { return { label: x.name + ' [' + x.status + ']', value: String(x.id) }; })),
                onChange: function (v) { updated('performance_id', Number(v || 0)); }
            }),
            el(Select, { key: 'venue', label: 'Площадка (ровно одна для scheduled)',
                value: selectedVenue, options: [{ label: '— Select venue —', value: '' }].concat(
                    options.venues.map(function (x) { return { label: x.name, value: String(x.id) }; })),
                onChange: selectedVenueChange
            }),
            el(Select, { key: 'event', label: 'Event state', value: meta.event_state || 'scheduled',
                options: ['scheduled', 'cancelled', 'postponed'].map(function (s) { return { value: s, label: s }; }),
                onChange: function (v) { updated('event_state', v); } }),
            el(Select, { key: 'sale', label: 'Sale state', value: meta.sale_state || 'unavailable',
                options: ['unavailable', 'on_sale', 'sold_out'].map(function (s) { return { value: s, label: s }; }),
                onChange: function (v) { updated('sale_state', v); } }),
            el(Select, { key: 'timezone', label: 'IANA timezone (must match venue)', value: currentTz,
                options: [{label:'— Choose timezone —',value:''}].concat(
                    options.timezones.map(function (s) { return { value: s, label: s }; })),
                onChange: function (v) { updated('show_timezone', v); setLocal(''); } }),
            el(C.TextControl, { key: 'local', label: 'Local date/time (editor-only input)',
                type: 'datetime-local', value: local || '',
                help: 'Use Convert; nonexistent / ambiguous DST times are rejected.',
                onChange: setLocal }),
            el(C.Button, { key: 'convert', variant: 'secondary', isBusy: working, disabled: working || !local,
                onClick: convertDate }, 'Convert local → UTC'),
            error && el(C.Notice, { key: 'err', status: 'error', isDismissible: true, onRemove: function () { setError(''); } }, error),
            el(C.TextControl, { key: 'utc', label: 'UTC start stored in WP', value: meta.show_start_utc || '',
                help: 'Format YYYY-MM-DD HH:MM:SS. For scheduled shows, convert local input above.', onChange: function (v) { updated('show_start_utc', v); } }),
            meta.show_start_utc && el('p', { key: 'readback' }, 'Venue local readback: ' + localized(meta.show_start_utc, currentTz)),
            el(C.TextControl, { key: 'end', label: 'UTC end (optional)', value: meta.show_end_utc || '', onChange: function (v) { updated('show_end_utc', v); } }),
            el(C.TextareaControl, { key: 'note', label: 'Show note, max 500 characters',
                value: meta.show_note || '', onChange: function (v) { updated('show_note', v); } }),
            el(C.TextareaControl, { key: 'change', label: 'Confirmed cancellation / postponement note',
                value: meta.change_notice || '', onChange: function (v) { updated('change_notice', v); } }),
            el('h3', { key: 'links-heading' }, 'Ticket links (HTTPS, max 5)'),
            el('p', { key: 'links-warning' }, 'No unverified URLs. on_sale requires a safe public HTTPS link.'),
        ];
        links.forEach(function (link, i) {
            rows.push(el('div', { key: 'link-' + i, style: { borderTop: '1px solid #ddd', paddingTop: '8px', marginTop: '8px' } },
                el(C.TextControl, { label: 'Operator / label #' + (i + 1), value: link.label || '', onChange: function (v) { updateLink(i, 'label', v); } }),
                el(C.TextControl, { label: 'HTTPS ticket URL', type: 'url', value: link.url || '', onChange: function (v) { updateLink(i, 'url', v); } }),
                el(C.CheckboxControl, { label: 'Primary', checked: !!link.primary, onChange: function (v) { updateLink(i, 'primary', v); } }),
                el(C.Button, { variant: 'tertiary', isDestructive: true, onClick: function () {
                    updated('ticket_links', links.filter(function (_, ix) { return ix !== i; }));
                } }, 'Remove')
            ));
        });
        rows.push(el(C.Button, { key: 'add', variant: 'secondary', disabled: links.length >= 5,
            onClick: function () { updated('ticket_links', links.concat([{ label: '', url: '', primary: links.length === 0 }])); }
        }, 'Add ticket link'));
        return el(Panel, { name: 'absurd-show-fields', title: 'ABSURD Show Details', className: 'absurd-show-details' }, rows);
    }
    wp.plugins.registerPlugin('absurd-show-editor', { render: Editor });
}());
