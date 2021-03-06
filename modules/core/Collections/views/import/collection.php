
{{ $app->assets(['collections:assets/import/parser.js'], $app['cockpit/version']) }}

<div>
    <ul class="uk-breadcrumb">
        <li><a href="@route('/collections')">@lang('Collections')</a></li>
        <li data-uk-dropdown="mode:'hover, delay:300'">

            <a href="@route('/collections/entries/'.$collection['name'])"><i class="uk-icon-bars"></i> {{ @$collection['label'] ? $collection['label']:$collection['name'] }}</a>

            <div class="uk-dropdown">
                <ul class="uk-nav uk-nav-dropdown">
                    <li class="uk-nav-header">@lang('Actions')</li>
                    <li><a href="@route('/collections/collection/'.$collection['name'])">@lang('Edit')</a></li>
                    <li class="uk-nav-divider"></li>
                    <li class="uk-text-truncate"><a href="@route('/collections/export/'.$collection['name'])" download="{{ $collection['name'] }}.collection.json">@lang('Export entries')</a></li>
                </ul>
            </div>

        </li>
        <li class="uk-active"><span>@lang('Import')</span></li>
    </ul>

</div>


<div class="uk-margin-top" riot-view>

    <div class="uk-viewport-height-1-3 uk-flex uk-flex-center uk-flex-middle" name="parse" data-step="parse" show="{step=='parse'}">
        <div class="uk-text-center">
            <i class="uk-h1 uk-icon-spinner uk-icon-spin"></i>
            <p class="uk-text-muted uk-text-large">@lang('Parsing file...')</p>
        </div>
    </div>

    <div class="uk-viewport-height-1-3 uk-flex uk-flex-center uk-flex-middle" name="process" data-step="process" show="{step=='process'}">
        <div class="uk-text-center">
            <i class="uk-h1 uk-icon-spinner uk-icon-spin"></i>
            <p class="uk-text-muted uk-text-large"><span name="progress"></span></p>
        </div>
    </div>

    <div name="step1" class="uk-pabel uk-panel-box uk-panel-card uk-text-center uk-viewport-height-1-3 uk-flex uk-flex-center uk-flex-middle" data-step="1" show="{step==1}">
        <div>

            <p>
                <img src="@url('assets:app/media/icons/import.svg')" width="100" height="100" data-uk-svg alt="Import">
            </p>

            <p class="uk-text-muted uk-text-large uk-margin-top">
                Drop a file here or <a class="uk-form-file">selecting one<input type="file"></a>
            </p>
        </div>
    </div>

    <div name="step2" data-step="1" show="{step==2}">
        
        <h2>{ file.name }</h2>
        <div class="uk-margin uk-text-muted">{ data.rows.length } @lang('Entries found.')</div>

        <table class="uk-table uk-table-border uk-table-striped uk-margin-top">
            <thead>
                <tr>
                    <th width="10"></th>
                    <th class="uk-text-small">@lang('Collection Field')</th>
                    <th width="30%" class="uk-text-small">@lang('Map Field')</th>
                    <th width="30%" class="uk-text-small">@lang('Filter')</th>
                </tr>
            </thead>
            <tbody class="uk-form">
                <tr each="{field,idx in fields}">
                    <td><span class="uk-badge uk-badge-danger" if="{field.required}" title="@lang('Required')">R</span></td>
                    <td>
                        <span if="{ field._lang }" class="uk-margin-left uk-margin-small-right uk-icon-globe uk-text-muted" title="@lang('Localized field')" data-uk-tooltip="pos:'left'"></span>
                        <span class="{field._lang && 'uk-text-muted'}">{ field.name }</span>
                    </td>
                    <td>
                        <div class="uk-form-select">
                            <a class="{ parent.mapping[field.name] ? 'uk-link-muted':''}"><i class="uk-icon-exchange" show="{parent.mapping[field.name]}"></i> { parent.mapping[field.name] || 'Select...'}</a>
                            <select class="uk-width-1-1" bind="mapping.{field.name}">
                                <option></option>
                                <option each="{h,hidx in data.headers}" value="{h}">{h}</option>
                            </select>
                        </div>
                        
                    </td>
                    <td>
                        <div class="uk-form-select">
                            <a><i class="uk-icon-bolt"></i></a>
                            <select class="uk-width-1-1"></select>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="uk-margin">
            <button class="uk-button uk-button-large uk-button-primary" onclick="{ doImport }">@lang('Import')</button>
            <a class="uk-margin-left" onclick="{restart}">@lang('Cancel')</a>
        </div>

    </div>

    <script type="view/script">

        var $this = this;

        this.mixin(RiotBindMixin);

        this.collection = {{ json_encode($collection) }};
        this.file = null;
        this.data = null;
        this.mapping = {};
        this.filter = {};
        this.step = 1;

        this.fields = [];

        this.collection.fields.forEach(function(field) {
            $this.fields.push(field);

            if (field.localize && App.$data.languages) {

                App.$data.languages.forEach(function(lang, f) {

                    f = App.$.extend({}, field);

                    f.name = f.name+'_'+lang;
                    f.required = false;
                    f.localize = false;
                    f._lang = lang

                    $this.fields.push(f);
                });
            }
        });

        restart() {

            this.data = null;
            this.mapping = {};
            this.filter = {};
            
            this.step = 1;
            this.step1.classList.remove('uk-dragover');
        }

        // STEP 1

        selectFile(file) {

            if (!file || ['application/json', 'text/csv'].indexOf(file.type) == -1) {
                return App.ui.notify("Only JSON and CSV files are supported.");
            }

            this.step = 'parse';
            this.update();

            ImportParser.parse(file).then(function(data) {
                
                $this.data = data;

                // auto-map fields
                $this.fields.forEach(function(f){
                    if (data.headers.indexOf(f.name) != -1) {
                        $this.mapping[f.name] = f.name;
                    }
                });
                
                $this.file = file;
                $this.step = 2;
                $this.update();
            }, function(msg) {
                App.ui.notify(msg, "danger");
                $this.step = 1;
                $this.update();
            });
        }

        this.step1.addEventListener('dragenter', function(e) {
            e.stopPropagation();
            e.preventDefault();
            this.classList.add('uk-dragover');
        }, false);

        this.step1.addEventListener('dragleave', function(e) {
            e.stopPropagation();
            e.preventDefault();
            this.classList.remove('uk-dragover');
        }, false);

        this.step1.addEventListener('dragover', function(e) {
            e.stopPropagation();
            e.preventDefault();
        }, false);

        this.step1.addEventListener('drop', function(e){

            e.stopPropagation();
            e.preventDefault();

            $this.selectFile(e.dataTransfer.files[0]);

        }, false);

        this.step1.addEventListener('change', function(e){
            e.stopPropagation();
            e.preventDefault();

            $this.selectFile(e.target.files[0]);

            // loosy hack
            setTimeout(function() {
                App.$(e.target).replaceWith(e.target.outerHTML);
            }, 100);

        }, false);

        // STEP 2


        // HELPER

        doImport() {

            if (!Object.keys(this.mapping || {}).length) {
                return App.ui.notify("Please define some field mappings first.");
            }

            var required = [];

            this.collection.fields.forEach(function(field) {
                if (field.required && !$this.mapping[field.name]) {
                    required.push('<strong>'+field.name+'</strong>');
                }
            });

            if (required.length) {
                return App.ui.notify("Required fields are not mapped:<div class='uk-margin-small-top'>"+required+"</div>");
            }

            var cnt    = 20,
                chunks = chunk(this.data.rows, cnt),
                chain  = Promise.resolve(),
                progress = 0;

            this.progress.innerHTML  = '0 %';
            this.step = 'process';

            chunks.forEach(function(chunk){

                chain = chain.then(function() {

                    return new Promise(function(resolve){

                        var entries = [], entry;

                        chunk.forEach(function(c) {

                            entry = {};

                            Object.keys($this.mapping).forEach(function(k){
                                entry[k] = c[k] || null;
                            });

                            entries.push(entry);
                        });

                        App.callmodule('collections:save',[$this.collection.name, entries]).then(function(data) {
                            
                            progress += cnt;

                            if (progress > $this.data.rows.length) {
                                progress = $this.data.rows.length;
                            }
                            
                            $this.progress.innerHTML = Math.ceil((progress/$this.data.rows.length)*100)+' %';

                            if (progress == $this.data.rows.length) {
                                App.ui.notify("Import completed.", "success");
                                $this.restart();
                                $this.update();
                            }

                            resolve(data && data.result);
                        });
                    });
                });
            });
        }


        function chunk(arr, len) {

            var chunks = [], i = 0, n = arr.length;

            while (i < n) {
                chunks.push(arr.slice(i, i += len));
            }

            return chunks;
        }



    </script>

</div>