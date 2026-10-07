<div id="clients">
    <div class="row">
        <div class="col-md-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Prospect Entry Information</h4>
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
                            <div class="col-xs-12 col-sm-9 col-md-9 col-lg-9">
                                <!-- Client entry-->
                                <form method="post" @submit.prevent="saveClient">
                                    <div class="row" style="padding:15px 0px">
                                        <div class="col-xs-12 col-sm-5 col-md-5 col-lg-5">
                                            <div class="form-group clearfix">
                                                <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Client
                                                    Type:</label>
                                                <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7">
                                                    <select class="form-control" v-if="areas.length == 0"></select>
                                                    <v-select v-bind:options="clientTypes" v-model="selectedClientType"
                                                        label="name" v-if="areas.length > 0"></v-select>
                                                </div>
                                                <div class="col-xs-12 col-sm-1 col-md-1 col-lg-1">
                                                    <a href="<?= base_url('client_type') ?>"
                                                        class="btn btn-xs btn-danger"
                                                        style="height: 25px; border: 0; width: 27px; margin-left: -22px;"
                                                        target="_blank" title="Add New Product"><i class="fa fa-plus"
                                                            aria-hidden="true" style="margin-top: 5px;"></i></a>
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Client
                                                    Id:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.client_id"
                                                        required>
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Client
                                                    Name:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.client_name"
                                                        required>
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Org.
                                                    Name:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.org_name">
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">E-mail:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.email">
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Address:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.address">
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Area:</label>
                                                <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7">
                                                    <select class="form-control" v-if="areas.length == 0"></select>
                                                    <v-select v-bind:options="areas" v-model="selectedArea" label="name"
                                                        v-if="areas.length > 0"></v-select>
                                                </div>
                                                <div class="col-xs-12 col-sm-1 col-md-1 col-lg-1">
                                                    <a href="<?= base_url('area') ?>" class="btn btn-xs btn-danger"
                                                        style="height: 25px; border: 0; width: 27px; margin-left: -22px;"
                                                        target="_blank" title="Add New Product"><i class="fa fa-plus"
                                                            aria-hidden="true" style="margin-top: 5px;"></i></a>
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Mobile:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.phone"
                                                        required>
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Office
                                                    Phone:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.org_mobile">
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Whatsapp:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <input type="text" class="form-control" v-model="client.w_number">
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Level:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <select class="form-control" v-model="client.level">
                                                        <option value="Entry">Entry Level</option>
                                                        <option value="Middle">Mid Level </option>
                                                        <option value="Top">Top Level</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-4 col-md-4 col-lg-4">Source:</label>
                                                <div class="col-xs-12 col-sm-8 col-md-8 col-lg-8">
                                                    <select class="form-control" v-model="client.source">
                                                        <option value="L">Leaflet </option>
                                                        <option value="FF">Face To Face</option>
                                                        <option value="FB">Facebook</option>
                                                        <option value="G">Google</option>
                                                        <option value="Y">Youtube </option>
                                                        <option value="F">Friend & References</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xs-12 col-sm-7 col-md-7 col-lg-7">

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3">Service:</label>
                                                <div class="col-xs-11 col-sm-8 col-md-8 col-lg-8">
                                                    <select class="form-control" v-if="softwares.length == 0"></select>
                                                    <v-select multiple v-bind:options="softwares"
                                                        v-model="selectedSoftware" label="soft_name"></v-select>
                                                </div>
                                                <div class="col-xs-1 col-sm-1 col-md-1 col-lg-1">
                                                    <a href="<?= base_url('requerment') ?>"
                                                        class="btn btn-xs btn-danger"
                                                        style="height: 25px; border: 0; width: 27px; margin-left: -22px;"
                                                        target="_blank" title="Add New Product"><i class="fa fa-plus"
                                                            aria-hidden="true" style="margin-top: 5px;"></i></a>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3">Sold
                                                    Client:</label>
                                                <div class="col-xs-12 col-sm-9 col-md-9 col-lg-9">
                                                    <label v-for="sold in softwares" class="checkbox-inline">
                                                        <input type="checkbox" :value="sold.soft_name"
                                                            v-model="selected">{{ sold.soft_name }}
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="form-group clearfix" style="display: none;"
                                                :style="{display: selected.length > 0 ? '' : 'none'}">
                                                <label class="control-label col-md-3">Project Name</label>
                                                <div class="col-md-9">
                                                    <v-select v-bind:options="projects" label="name"
                                                        v-model="selectedProject" @input="getProjectsById">
                                                    </v-select>
                                                </div>
                                            </div>

                                            <!-- <div class="form-group clearfix" style="display: none;"
                                                :style="{display: selected.length > 0 ? '' : 'none'}">
                                                <label class="control-label col-md-3">Project Id</label>
                                                <div class="col-md-9">
                                                    <v-select v-bind:options="all_projects" label="project_id"
                                                        v-model="selectedProjectId" @input="getProjectTypeById">
                                                    </v-select>
                                                </div>
                                            </div> -->

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3">Reminder:</label>
                                                <div class="col-xs-11 col-sm-9 col-md-9 col-lg-9">
                                                    <input type="date" class="form-control" v-model="client.reminder">
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3">Comment:</label>
                                                <div class="col-xs-12 col-sm-9 col-md-9 col-lg-9">
                                                    <textarea class="form-control" style="height: 80px !important"
                                                        v-model="client.comment"></textarea>
                                                </div>
                                            </div>

                                            <div class="form-group clearfix">
                                                <label
                                                    class="control-label col-xs-12 col-sm-9 col-md-9 col-lg-9"></label>
                                                <div class="col-md-3 pull-right">
                                                    <input type="submit" name="submit"
                                                        class="btn btn-primary pull-right btn-block" value="Save">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Client entry -->
                            </div><!-- /.col -->
                            <div class="col-xs-12 col-sm-3 col-md-3 col-log-3">
                                <div class="row" style="margin-top: 10px;">
                                    <div class="col-xs-12">
                                        <div class="form-group">
                                            <label for="date" class="col-xs-2">Date</label>
                                            <div class="col-xs-10">
                                                <input type="date" v-model="client.date" class="form-control">
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="date" class="col-xs-2">Note</label>
                                            <div class="col-xs-10">
                                                <textarea class="form-control" v-model="client.note"
                                                    style="height: 200px !important"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.row -->

    <div class="row">

    </div>

    <div class="widget-box">
        <div class="widget-header">
            <h4 class="widget-title">Prospect List</h4>
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
                    <div class="col-sm-12 form-inline">
                        <div class="form-group">
                            <label for="filter" class="sr-only">Filter</label>
                            <input type="text" class="form-control" v-model="filter" placeholder="Filter">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <datatable :columns="columns" :data="clients" :filter-by="filter"
                                style="margin-bottom: 5px;">
                                <template scope="{ row }">
                                    <tr>
                                        <td>{{ row.add_time | dateOnly('DD-MM-YYYY') }}</td>
                                        <td>{{ row.client_id }}</td>
                                        <td>{{ row.client_name }}</td>
                                        <td>{{ row.org_name }}</td>
                                        <td>{{ row.name }}</td>
                                        <td>{{ row.phone }}</td>
                                        <td>{{ row.w_number }}</td>
                                        <td>
                                            <span v-for="(req, ind) in row.requirements">
                                                {{ req.soft_name }} {{ row.requirements.length-1 == ind ? ' ' : ' , ' }}
                                            </span>
                                        </td>
                                        <td>{{ row.officer }}</td>
                                        <td>
                                            <button type="button" class="button edit" @click="editClient(row)">
                                                <i class="fa fa-pencil"></i>
                                            </button>
                                            <button type="button" class="button" @click="deleteClient(row.id)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                            <button type="button" class="button edit btn-success btn btn-sm"
                                                data-toggle="modal" data-target="#exampleModalCenter"
                                                @click="viewDetails(row.id)">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </datatable>
                            <datatable-pager v-model="page" type="abbreviated" :per-page="per_page"
                                style="margin-bottom: 50px;"></datatable-pager>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- model -->
    <div style="display:none" :style="{display: show ? '' : 'none'}" class="modal fade" id="exampleModalCenter"
        tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">Client Discussion Summary
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="card-area" v-for="detail in details">
                        <h4>Date: {{ detail.date }}</h4>
                        <p style="white-space: pre-line">{{ detail.note }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- end -->
</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
Vue.component('v-select', VueSelect.VueSelect);
const app = new Vue({
    el: '#clients',
    data() {
        return {
            clientId: '<?php echo $clientId; ?>',
            client: {
                id: null,
                client_id: '',
                client_type_id: null,
                project_id: '',
                client_name: '',
                org_name: '',
                phone: '',
                email: '',
                w_number: '',
                org_mobile: '',
                address: '',
                area_id: '',
                requirement: [],
                level: null,
                source: '',
                sold: [],
                reminder: '',
                comment: '',
                date: '',
                note: ''
            },
            projects: [],
            selectedProject: null,
            all_projects: [],
            selectedProjectId: null,
            selected: [],
            areas: [],
            clients: [],
            softwares: [],
            selectedSoftware: [],
            clientTypes: [],
            selectedClientType: null,
            selectedArea: null,
            checkMobile: /^01[13-9][\d]{8}$/,
            columns: [{
                    label: 'Date',
                    field: 'add_time',
                    align: 'center',
                    filterable: false
                },
                {
                    label: 'Client Id',
                    field: 'client_id',
                    align: 'center'
                },
                {
                    label: 'Client Name',
                    field: 'client_name',
                    align: 'center'
                },
                {
                    label: 'Org. Name',
                    field: 'org_name',
                    align: 'center'
                },
                {
                    label: 'Area',
                    field: 'name',
                    align: 'center'
                },
                {
                    label: 'Phone',
                    field: 'phone',
                    align: 'center'
                },
                {
                    label: 'Whatsapp Num.',
                    field: 'w_number',
                    align: 'center'
                },
                {
                    label: 'Service',
                    field: 'requirement',
                    align: 'center',
                    filterable: false
                },
                {
                    label: 'Off. Name',
                    field: '',
                    align: 'center',
                    filterable: false
                },
                {
                    label: 'Action',
                    align: 'center',
                    filterable: false
                }
            ],
            page: 1,
            per_page: 50,
            filter: '',
            show: false,
            details: []
        }
    },

    filters: {
        dateOnly(datetime, format) {
            return moment(datetime).format(format);
        }
    },

    created() {
        this.getAreas();
        this.getClient();
        this.getClientTypes();
        this.getSoftware();
        this.getProjects();

        if (this.clientId != 0) {
            this.getSoldClient();
        }
    },

    methods: {
        getAreas() {
            axios.get('/get-areas').then(res => {
                this.areas = res.data;
            })
        },
        getClientTypes() {
            axios.get('/get-clientType').then(res => {
                this.clientTypes = res.data;
            })
        },
        getProjects() {
            axios.get('/get-projects').then(res => {
                this.projects = res.data;
            })
        },
        getProjectsById() {

            let form = {
                project_id: this.selectedProject.id
            }

            axios.post('/get-projects-by-projectid', form).then(res => {
                this.all_projects = res.data;
            })

        },
        getSoftware() {
            axios.get('/get-software').then(res => {
                this.softwares = res.data;
            })
        },
        getClient() {
            let filter = {
                type: 'p',
                userId: "<?php echo $this->session->userdata('userid') ?>"
            }
            axios.post('/get-client', filter).then(res => {
                this.clients = res.data;
            })
        },
        saveClient() {
            if (this.selectedArea == null) {
                alert('Select area');
                return;
            }

            if (this.selectedClientType == null) {
                alert('Plz Select Client Type');
                return;
            }

            if (this.checkMobile.test(this.client.phone) == false) {
                alert('Mobile is not valid');
                return;
            }
            if (this.client.source == '') {
                alert('Select source');
                return;
            }

            this.client.project_id = this.selectedProject != null ? this
                .selectedProject.id : null;
            this.client.area_id = this.selectedArea.id;
            this.client.client_type_id = this.selectedClientType.id;



            this.client.requirement = [];
            this.selectedSoftware.forEach(re => {
                this.client.requirement.push(re.id);
            });
            this.client.sold = this.selected;

            let url = '';

            if (this.client.id != null) {
                url = '/update-client';
            } else {
                url = '/add-client';
                delete this.client.id
            }

            axios.post(url, this.client)
                .then(res => {
                    let r = res.data;
                    if (r.success) {
                        alert(r.message);
                        this.resetForm();
                        this.getClient();
                        window.location.reload()
                    } else {
                        alert(r.message);
                    }
                })
        },
        editClient(client) {

            let keys = Object.keys(this.client);
            keys.forEach(key => {
                this.client[key] = client[key];
            })

            this.selectedArea = {
                id: client.aid,
                name: client.name
            }

            this.selectedClientType = {
                id: client.id,
                name: client.client_type_name
            }

            this.selectedSoftware = Object.values(client.requirements);

            let selectedData = this.client.sold.split(",");

            this.selected = selectedData;
        },
        deleteClient(id) {
            let deleteConfirm = confirm('Are you sure?');
            if (deleteConfirm == false) {
                return;
            }
            axios.post('/delete-client', {
                clientId: id
            }).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.getClient();
                }
            })
        },
        getSoldClient() {
            axios.post('/get-client', {
                    clientId: this.clientId
                })
                .then(res => {
                    let client = res.data[0]
                    Object.keys(client).forEach((key) => {
                        this.client[key] = client[key]
                    })

                    this.selectedArea = {
                        id: client.aid,
                        name: client.name
                    }

                    this.selectedClientType = {
                        id: client.id,
                        name: client.client_type_name
                    }

                    this.selectedSoftware = Object.values(client.requirements);

                    let selectedData = client.sold.split(",");

                    this.selected = selectedData;
                })
        },
        resetForm() {
            let keys = Object.keys(this.client);
            keys.forEach(key => {
                if (typeof(this.client[key]) == 'string') {
                    this.client[key] = '';
                } else if (typeof(this.client[key]) == 'number') {
                    this.client[key] = 0;
                }
                this.selectedArea = null;
                this.selectedSoftware = null;
            })
            this.selected = []
            this.client.id = null;
        },
        viewDetails(clientId) {
            this.show = true;
            axios.post('/get_clientdetails', {
                    clientId: clientId
                })
                .then(res => {
                    this.details = res.data;
                })
        }
    }
})
</script>
<style type="text/css">
.v-select {
    margin-bottom: 5px;
}

.v-select.open .dropdown-toggle {
    border-bottom: 1px solid #ccc;
}

.v-select .dropdown-toggle {
    padding: 0px;
    height: 28px;
}

.v-select input[type=search],
.v-select input[type=search]:focus {
    margin: 0px;
}

.v-select .vs__selected-options {
    overflow: hidden;
    flex-wrap: nowrap;
}

.v-select .selected-tag {
    margin: 2px 1px;
    white-space: nowrap;
    /*position:absolute;
		left: 
0px;*/



}

.v-select .vs__actions {
    margin-top: -5px;
}

.v-select .dropdown-menu {
    width: auto;
    overflow-y: auto;
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #e5e5e5;
    background: #e5e5e5;
}

h5 {
    font-size: 22px;
}

.modal-dialog,
.modal-content {
    height: 90%;
}

.modal-body {
    max-height: calc(100% - 120px);
    overflow-y: scroll;
}

.card-area {
    border: 1px solid #ccc;
    border-radius: 3px;
    padding: 5px 10px;
    margin-bottom: 5px;
}
</style>