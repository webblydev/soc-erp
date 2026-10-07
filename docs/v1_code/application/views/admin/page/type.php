<div id="types">
    <div class="row">
        <div class="col-md-6">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Type Information</h4>
                    <div class="widget-toolbar">
                        <a href="#" data-action="collapse">
                            <i class="ace-icon fa fa-chevron-up"></i>
                        </a>

                        <a href="#" data-action="close">
                            <i class="ace-icon fa fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="widget-body">
                    <div class="widget-main">
                        <div class="row">
                            <div class="col-md-8 col-md-offset-2">
                                <div v-if="show" class="alert text-center" :class="success" role="alert">
                                    {{ message }}
                                </div>
                                <form method="type" class="form-inline" @submit.prevent="saveType">
                                    <div class="form-group">
                                        <label for="email">Name <span class="text-danger">*</span>:</label>
                                        <input type="text" class="form-control" v-model="type.name" required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-info">Save</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Type List</h4>
                    <div class="widget-toolbar">
                        <a href="#" data-action="collapse">
                            <i class="ace-icon fa fa-chevron-up"></i>
                        </a>

                        <a href="#" data-action="close">
                            <i class="ace-icon fa fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="widget-body">
                    <div class="widget-main">
                        <div class="row">
                            <div class="col-md-8 col-md-offset-2">
                                <div v-if="isShow" class="alert text-center" :class="success" role="alert">
                                    {{ message }}
                                </div>
                            </div>
                            <div class="col-sm-12 form-inline">
                                <div class="form-group">
                                    <label for="filter" class="sr-only">Filter</label>
                                    <input type="text" class="form-control" v-model="filter" placeholder="Filter">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <datatable :columns="columns" :data="types" :filter-by="filter">
                                        <template scope="{ row }">
                                            <tr>
                                                <td>{{ row.sl }}</td>
                                                <td>{{ row.name }}</td>
                                                <td>
                                                    <a href="" @click.prevent="edit(row)"><i class="fa fa-pencil-square-o"></i></a>&nbsp;
                                                    <?php if ($this->session->userdata('type') == 'a') { ?>
                                                        <a href="" @click.prevent="deleteType(row.id)"><i class="fa fa-trash"></i></a>
                                                    <?php }?>
                                                </td>
                                            </tr>
                                        </template>
                                    </datatable>
                                    <datatable-pager v-model="page" type="abbreviated" :per-page="per_page"></datatable-pager>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>

<script>
    const app = new Vue({
        el: '#types',
        data: {
            type: {
                id: null,
                name: ''
            },
            message: '',
            show: false,
            isShow: false,
            success: '',
            types: [],
            columns: [
                { label: 'Serial', field: 'sl', align: 'center' },
                { label: 'Type Name', field: 'name', align: 'center' },
                { label: 'Action', align: 'center', filterable: false }
            ],
            page: 1,
            per_page: 10,
            filter: ''
        },
        async created() {
            await this.getTypes();
        },
        methods: {
            async getTypes() {
                await axios.get('get-types')
                .then(res => {
                    this.types = res.data.map((item, sl) => {
                        item.sl = sl + 1;
                        return item;
                    });
                })
            },
            async saveType() {
                let url = '';

                if(this.type.id != null) {
                    url = '/update-type';
                } else {
                    url = '/add-type';
                    delete this.type.id
                }

                await axios.post(url, this.type)
                .then(res => {
                    this.show = true;

                    if(res.data.success) {
                        this.message = res.data.message;
                        this.type.id = null;
                        this.type.name = '';
                        this.success = 'alert-success';

                        this.getTypes();

                        setTimeout(()=>{
                            this.show = false;
                            this.message = '';
                        }, 3000);
                    } else {
                        this.message = res.data.message;
                        this.success = 'alert-danger';

                        setTimeout(()=>{
                            this.show = false;
                            this.message = '';
                        }, 3000);
                    }
                })
                .catch(err => {
                    this.show = true;
                    this.message = err.response.data.message;
                    this.success = 'alert-danger';

                    setTimeout(()=>{
                        this.show = false;
                        this.message = '';
                    }, 3000);
                })
            },
            edit(type) {
                Object.keys(this.type).forEach(key => {
                    this.type[key] = type[key];
                })
            },
            async deleteType(id) {
                if(confirm('Are you sure ?')) {
                    await axios.post('/delete-type', { id: id})
                    .then(res => {
                        this.isShow = true;
                        if(res.data.success) {
                            this.message = res.data.message;
                            this.success = 'alert-success';
                            this.getTypes();
                            setTimeout(()=>{
                                this.isShow = false;
                                this.message = '';
                            }, 3000);

                        } else {
                            this.message = res.data.message;
                            this.success = 'alert-danger';
                            setTimeout(()=>{
                                this.isShow = false;
                                this.message = '';
                            }, 3000);
                        }
                    })
                    .catch(err => {
                        this.isShow = true;
                        this.message = err.response.data.message;
                        this.success = 'alert-danger';
                        setTimeout(()=>{
                            this.isShow = false;
                            this.message = '';
                        }, 3000);
                    })
                }
            }
        }
    })
</script>