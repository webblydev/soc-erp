<div id="report">
    <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Client List</h4>
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
                            <form class="form-inline" id="searchForm">
                                <div class="form-group col-md-3">
                                    <label class="col-md-5">Search Type</label>
                                    <div class="col-md-7">
                                        <select class="form-control" v-model="searchType" @change="onChangeSearchType"
                                            style="padding:0px">
                                            <option value="">All</option>
                                            <option value="p">Knock Client</option>
                                            <option value="s">Sold Client</option>
                                            <option value="source">By Source</option>
                                            <option value="area">By Area</option>
                                            <option value="clientType">By ClientType</option>
                                            <option value="software">By Requirement</option>
                                            <option value="team" v-if="userType == 'a'">By Team</option>
                                            <option value="user" v-if="userType == 'a'">By Office</option>
                                        </select>

                                    </div>
                                </div>
                                <div class="form-group col-md-3" style="display:none;"
                                    v-bind:style="{display: searchType == 'area' && areas.length > 0 ? '' : 'none'}">
                                    <label class="col-md-3">Area</label>
                                    <div class="col-md-9">
                                        <v-select v-bind:options="areas" v-model="selectedArea" label="name"></v-select>
                                    </div>
                                </div>

                                <div class="form-group col-md-3" style="display:none;"
                                    v-bind:style="{display: searchType == 'clientType' && clientTypes.length > 0 ? '' : 'none'}">
                                    <label class="col-md-3">Client type</label>
                                    <div class="col-md-9">
                                        <v-select v-bind:options="clientTypes" v-model="selectedClientType"
                                            label="name"></v-select>
                                    </div>
                                </div>

                                <div class="form-group col-md-3" style="display:none;"
                                    v-bind:style="{display: searchType == 'software' && requirements.length > 0 ? '' : 'none'}">
                                    <label class="col-md-4">Requirement</label>
                                    <div class="col-md-8">
                                        <v-select v-bind:options="requirements" v-model="selectedSoftware"
                                            label="soft_name"></v-select>
                                    </div>
                                </div>
                                <div class="form-group col-md-2" style="display:none;"
                                    v-bind:style="{display: searchType == 'team' ? '' : 'none'}">
                                    <label class="col-md-3">Team</label>
                                    <div class="col-md-9">
                                        <select class="form-control" v-model="teamName" required style="width: 100%;">
                                            <option value="a">Team A</option>
                                            <option value="b">Team B</option>
                                            <option value="c">Team C</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group col-md-3" style="display:none;"
                                    v-bind:style="{display: searchType == 'source' ? '' : 'none'}">
                                    <label class="col-md-3">Source</label>
                                    <div class="col-md-9">
                                        <select class="form-control" v-model="source" required style="width: 100%;">
                                            <option value="L">Leaflet </option>
                                            <option value="FF">Face To Face</option>
                                            <option value="FB">Facebook</option>
                                            <option value="G">Google</option>
                                            <option value="Y">Youtube </option>
                                            <option value="F">Friend & References</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group col-md-3" style="display:none;"
                                    v-bind:style="{display: searchType == 'user' && users.length > 0 ? '' : 'none'}">
                                    <label class="col-md-4">Officer</label>
                                    <div class="col-md-8">
                                        <v-select v-bind:options="users" v-model="selectedUser" label="name"></v-select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="formdate" class="col-md-3">Form:</label>
                                    <div class="col-md-8">
                                        <input type="date" class="form-control" v-model="dateFrom">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="formdto" class="col-md-3">To:</label>
                                    <div class="col-md-8">
                                        <input type="date" class="form-control" v-model="dateTo">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <input type="submit" class="btn btn-success" value="Search"
                                        @click.prevent="getSearchResult">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /.col -->
    </div><!-- /.row -->
    <div class="row" style="display: none;" :style="{display: clients.length > 0 ? '' : 'none'}">
        <div class="col-md-12">
            <a href="" style="margin: 7px 0;display:block;width:50px;" v-on:click.prevent="print">
                <i class="fa fa-print"></i> Print
            </a>
            <div class="table-responsive" id="reportContent">
                <table class="record-table">
                    <thead>
                        <th>Serial</th>
                        <th>Client Id</th>
                        <th>Client Name</th>
                        <th>Org. Name</th>
                        <th>Phone</th>
                        <th>Area</th>
                        <th>Level</th>
                        <th>Source</th>
                        <th>Service</th>
                        <th>officer</th>
                        <th>Action</th>
                    </thead>
                    <tbody>
                        <tr v-for="client in clients">
                            <td>{{ client.sl}}</td>
                            <td>{{ client.client_id}}</td>
                            <td>{{ client.client_name}}</td>
                            <td>{{ client.org_name}}</td>
                            <td>{{ client.phone}}</td>
                            <td>{{ client.name}}</td>
                            <td>{{ client.level}}</td>
                            <td>
                                <span v-if="client.source == 'L'">Leaflet</span>
                                <span v-else-if="client.source == 'FF'">Face To Face</span>
                                <span v-else-if="client.source == 'FB'">Facebook</span>
                                <span v-else-if="client.source == 'G'">Google</span>
                                <span v-else-if="client.source == 'Y'">Youtube</span>
                                <span v-else>Friend & References</span>
                            </td>
                            <td>
                                <span v-for="(req, ind) in client.requirements">
                                    {{ req.soft_name }} {{ client.requirements.length-1 == ind ? ' ' : ' , ' }}
                                </span>
                            </td>
                            <td>{{ client.officer}}</td>
                            <td style="text-align: center;">
                                <button type="button" class="button edit" data-toggle="modal" data-target="#myModal"
                                    @click="service = client">
                                    View
                                </button>
                                <?php if ($this->session->userdata('type') == 'a') { ?>
                                <a href="" :href="`/client-entry/${client.id}`" class="btn btn-xs"><i
                                        class="fa fa-pencil"></i></a>
                                <?php } ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- modal -->
    <div id="myModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Client Information</h4>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <table class="client-view" width="100%">
                                <tr>
                                    <td width="20%"><strong>Client Id</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.client_id }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Client Name</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.client_name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Org. Name</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.org_name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Phone</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.phone }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Org. Mobile</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.org_mobile }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Whatsapp Num.</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.w_number }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Aare</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Address</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.address }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>E-mail</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.email }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Service </strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-for="(req, ind) in service.requirements">
                                            {{ req.soft_name }} {{ service.requirements.length-1 == ind ? ' ' : ' , ' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Sold Status</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-for="sold in serviceSold(service.sold)">
                                            <span>{{ sold }}</span>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Cus. Level</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.level }} Level</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Reminder Date</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.reminder }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Source</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-if="service.source == 'L'">Leaflet</span>
                                        <span v-else-if="service.source == 'FF'">Face To Face</span>
                                        <span v-else-if="service.source == 'FB'">Facebook</span>
                                        <span v-else-if="service.source == 'G'">Google</span>
                                        <span v-else-if="service.source == 'Y'">Youtube</span>
                                        <span v-else>Friend & References</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Comment</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <p style="white-space: pre-line">{{ service.comment }}</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- modal -->

</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>
<script>
Vue.component('v-select', VueSelect.VueSelect);
const app = new Vue({
    el: '#report',
    data: {
        searchType: '',
        dateFrom: moment().format('YYYY-MM-DD'),
        dateTo: moment().format('YYYY-MM-DD'),
        teamName: '',
        source: '',
        areas: [],
        selectedArea: null,
        requirements: [],
        selectedSoftware: null,
        users: [],
        clientTypes: [],
        selectedClientType: null,
        selectedUser: null,
        clients: [],
        service: {},
        type: '',
        userType: '<?php echo $this->session->userdata('type') ?>',
        userId: '<?php echo $this->session->userdata('userid') ?>'
    },
    created() {

    },
    methods: {
        onChangeSearchType() {
            if (this.searchType == 'area') {
                this.getAreas();
            } else if (this.searchType == 'clientType') {
                this.getClientTypes();
            } else if (this.searchType == 'user') {
                this.getUsers();
            } else if (this.searchType == 'software') {
                this.getSoftware();
            } else if (this.searchType == 'p') {
                this.type = 'p'
            } else if (this.searchType == 's') {
                this.type = 's'
            }
        },
        getAreas() {
            axios.get('/get-areas').then(res => {
                this.areas = res.data;
            })
        },
        getSoftware() {
            axios.get('/get-software').then(res => {
                this.requirements = res.data;
            })
        },
        getUsers() {
            axios.get('/get-users').then(res => {
                this.users = res.data;
            })
        },
        getClientTypes() {
            axios.get('/get-clientType').then(res => {
                this.clientTypes = res.data;
            })
        },
        getSearchResult() {
            this.clients = [];
            if (this.searchType != 'area') {
                this.selectedArea = null;
            }

            if (this.searchType != 'software') {
                this.selectedSoftware = null;
            }

            if (this.searchType != 'user') {
                this.selectedUser = null;
            }
            if (this.searchType != 'team') {
                this.teamName = '';
            }
            if (this.searchType != 'source') {
                this.source = '';
            }
            if (this.searchType != 's' && this.searchType != 'p') {
                this.type = '';
            }


            if (this.searchType == '') {
                let search = {
                    dateFrom: this.dateFrom,
                    dateTo: this.dateTo,
                    userId: this.userType == 'a' ? '' : this.userId
                }
                axios.post("/get-client", search).then(res => {
                    this.clients = res.data.filter((e) => {
                        return e.sold != "";
                    });
                })
            } else {
                let userId = '';
                if (this.selectedUser != null) {
                    userId = this.selectedUser.id;
                } else if (this.userType == 'a') {
                    userId = '';
                } else {
                    userId = this.userId;
                }

                let data = {
                    areaId: this.selectedArea == null || this.selectedArea.id == '' ? '' : this.selectedArea
                        .id,
                    clientTypeId: this.selectedClientType == null || this.selectedClientType.id == '' ? '' :
                        this.selectedClientType
                        .id,
                    requirementId: this.selectedSoftware == null || this.selectedSoftware.id == '' ? '' :
                        this.selectedSoftware.id,
                    userId: userId,
                    teamName: this.teamName == '' ? '' : this.teamName,
                    source: this.source == '' ? '' : this.source,
                    type: this.type == '' ? '' : this.type,
                    dateFrom: this.dateFrom,
                    dateTo: this.dateTo,
                }
                axios.post('/get-client', data)
                    .then(res => {
                        this.clients = res.data.filter((e) => {
                            return e.sold != "";
                        })
                    })
            }
        },
        serviceSold(sold = '') {
            let arr = sold.split(",");
            return arr;
        },
        async print() {
            let reportContent = `
					<div class="container">
						<div class="row">
							<div class="col-xs-12 text-center">
								<h3 style="text-align:center">Client List</h3>
							</div>
						</div>
						<div class="row">
							<div class="col-xs-12">
								${document.querySelector('#reportContent').innerHTML}
							</div>
						</div>
					</div>
				`;

            var reportWindow = window.open('', 'PRINT', `height=${screen.height}, width=${screen.width}`);
            reportWindow.document.write(`
					<div class="row">
						<div class="col-md-12">
							<h2 style="text-align:center">Link-Up Technology Ltd.</h2>
						</div>
					</div>
				`);

            reportWindow.document.head.innerHTML += `
					<style>
						.record-table{
							width: 100%;
							border-collapse: collapse;
						}
						.record-table th, .record-table td{
							padding: 3px;
							border: 1px solid #454545;
						}
						.record-table th{
							text-align: center;
						}
					</style>
				`;
            reportWindow.document.body.innerHTML += reportContent;

            let rows = reportWindow.document.querySelectorAll('.record-table tr');
            rows.forEach(row => {
                row.lastChild.remove();
            })

            reportWindow.focus();
            await new Promise(resolve => setTimeout(resolve, 1000));
            reportWindow.print();
            reportWindow.close();
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
		left: 0px;*/
}

.v-select .vs__actions {
    margin-top: -5px;
}

.v-select .dropdown-menu {
    width: auto;
    overflow-y: auto;
}

.record-table {
    width: 100%;
}

.record-table thead {
    background-color: #0097df;
    color: white;
}

.record-table th,
.record-table td {
    padding: 3px;
    border: 1px solid #454545;
}




.record- table th {
    text-align: center;
}

@media print {

    .record-table th,
    .record-table td {
        border: 1px solid #454545 !important;
    }
}
</style>