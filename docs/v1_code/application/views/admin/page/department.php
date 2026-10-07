<div id="departments">
    <div class="row">
        <div class="col-md-6">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Department Information</h4>
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
                                <form method="post" class="form-inline" @submit.prevent="saveDepartment">
                                    <div class="form-group">
                                        <label for="email">Name <span class="text-danger">*</span>:</label>
                                        <input type="text" class="form-control" v-model="department.name" required>
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
                    <h4 class="widget-title">Department List</h4>
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
                                    <datatable :columns="columns" :data="departments" :filter-by="filter">
                                        <template scope="{ row }">
                                            <tr>
                                                <td>{{ row.sl }}</td>
                                                <td>{{ row.name }}</td>
                                                <td>
                                                    <a href="" @click.prevent="edit(row)"><i class="fa fa-pencil-square-o"></i></a>&nbsp;
                                                    <?php if ($this->session->userdata('type') == 'a') { ?>
                                                        <a href="" @click.prevent="deleteDepartment(row.id)"><i class="fa fa-trash"></i></a>
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

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>

<script>
    const app = new Vue({
        el: '#departments',
        data: {
            department: {
                id: null,
                name: ''
            },
            message: '',
            show: false,
            isShow: false,
            success: '',
            departments: [],
            columns: [
                { label: 'Serial', field: 'sl', align: 'center' },
                { label: 'Department Name', field: 'name', align: 'center' },
                { label: 'Action', align: 'center', filterable: false }
            ],
            page: 1,
            per_page: 10,
            filter: ''
        },
        async created() {
            await this.getDepartments();
        },
        methods: {
            async getDepartments() {
                await axios.get('get-departments')
                .then(res => {
                    this.departments = res.data.map((item, sl) => {
                        item.sl = sl + 1;
                        return item;
                    });
                })
            },
            async saveDepartment() {
                let url = '';

                if(this.department.id != null) {
                    url = '/update-department';
                } else {
                    url = '/add-department';
                    delete this.department.id
                }

                await axios.post(url, this.department)
                .then(res => {
                    this.show = true;

                    if(res.data.success) {
                        this.message = res.data.message;
                        this.department.name = '';
                        this.department.id = null;
                        this.success = 'alert-success';

                        this.getDepartments();

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
            edit(department) {
                Object.keys(this.department).forEach(key => {
                    this.department[key] = department[key];
                })
            },
            async deleteDepartment(id) {
                if(confirm('Are you sure ?')) {
                    await axios.post('/delete-department', { id: id})
                    .then(res => {
                        this.isShow = true;
                        if(res.data.success) {
                            this.message = res.data.message;
                            this.success = 'alert-success';
                            this.getDepartments();
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