<div id="clients">
    <div class="row">
        <div class="col-md-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Prospects List</h4>
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
                            <form method="post" @submit.prevent="searchClient">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group clearfix">
                                            <div class="form-group">
                                                <label for="employee" class="col-md-3">Member</label>
                                                <div class="col-md-9">
                                                    <select class="form-control" v-if="members.length == 0"></select>
                                                    <v-select v-bind:options="members" v-model="selectedMember"
                                                        label="display_name" v-if="members.length > 0"></v-select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="formdate" class="col-md-3">Form:</label>
                                        <div class="col-md-9">
                                            <input type="date" name="formdate" class="form-control"
                                                v-model="client.dateFrom">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="formdate" class="col-md-3">To:</label>
                                        <div class="col-md-9">
                                            <input type="date" name="todate" class="form-control"
                                                v-model="client.dateTo">
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <input type="submit" name="submit" value="Search" class="btn btn-info">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.row -->

    <div class="row" v-if="showDiv">
        <div class="col-sm-6 form-inline">
            <input type="text" class="form-control" placeholder="Search" v-model="filter">
        </div>
        <div class="col-sm-6 form-inline">
            <a href="" style="margin: 7px 20px;display:block;width:50px; float:right" v-on:click.prevent="print">
                <i class="fa fa-print"></i> Print
            </a>
        </div>
        <div class="col-md-12" id="reportContent">
            <table class="record-table">
                <datatable :columns="columns" :data="clients" :filter-by="filter" style="margin-bottom: 5px;">
                    <template scope="{ row }">
                        <tr>
                            <td>{{ row.add_time | dateOnly('DD-MM-YYYY') }}</td>
                            <td>{{ row.client_id }}</td>
                            <td>{{ row.client_name }}</td>
                            <td>{{ row.org_name }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.phone }}</td>
                            <td>
                                <span v-if="row.source == 'L'">Leaflet</span>
                                <span v-else-if="row.source == 'FF'">Face To Face</span>
                                <span v-else-if="row.source == 'FB'">Facebook</span>
                                <span v-else-if="row.source == 'G'">Google</span>
                                <span v-else-if="row.source == 'Y'">Youtube</span>
                                <span v-else>Friend</span>
                            </td>
                            <td>
                                <span v-for="(req, ind) in row.requirements">
                                    {{ req.soft_name }} {{ row.requirements.length-1 == ind ? ' ' : ' , ' }}
                                </span>
                            </td>
                            <td>{{ row.officer }}</td>
                            <td class="action">
                                <button type="button" class="button edit" data-toggle="modal" data-target="#myModal"
                                    @click="service = row"> View </button>
                                <a href="" :href="`/client-entry/${row.id}`" class="btn btn-xs
                            "><i class="fa fa-pencil"></i></a>
                            </td>
                        </tr>
                    </template>
                </datatable>
                <datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;">
                </datatable-pager>


                <!-- <thead>
                    <th>SL</th>
                    <th>Date</th>
                    <th>Client Id</th>
                    <th>Client Name</th>
                    <th>Org. Name</th>
                    <th>Area</th>
                    <th>Phone</th>
                    <th>Source</th>
                    <th>Service</th>
                    <th>Office</th>
                    <th>Action</th>
                </thead>
                <tbody>
                    <tr v-for="(row, ind) in clients">
                        <td>{{ ind + 1 }}</td>
                        <td>{{ row.add_time | dateOnly('DD-MM-YYYY') }}</td>
                        <td>{{ row.client_id }}</td>
                        <td>{{ row.client_name }}</td>
                        <td>{{ row.org_name }}</td>
                        <td>{{ row.name }}</td>
                        <td>{{ row.phone }}</td>
                        <td>
                            <span v-if="row.source == 'L'">Leaflet</span>
                            <span v-else-if="row.source == 'FF'">Face To Face</span>
                            <span v-else-if="row.source == 'FB'">Facebook</span>
                            <span v-else-if="row.source == 'G'">Google</span>
                            <span v-else-if="row.source == 'Y'">Youtube</span>
                            <span v-else>Friend</span>

                        </td>
                        <td>
                            <span v-for="(req, ind) in row.requirements">
                                {{ req.soft_name }} {{ row.requirements.length-1 == ind ? ' ' : ' , ' }}
                            </span>
                        </td>
                        <td>{{ row.officer }}</td>
                        <td class="action">
                            <button type="button" class="button edit" data-toggle="modal" data-target="#myModal"
                                @click="service = row"> View </button>
                            <a href="" :href="`/client-entry/${row.id}`" class="btn btn-xs"><i
                                    class="fa fa-pencil"></i></a>
                        </td>
                    </tr>
                </tbody> -->
            </table>
        </div>


    </div>

    <!-- modal -->
    <div id="myModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Prospect Information</h4>
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
                                    <td width="20%"><strong>Client Type</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.client_type_name }}</td>
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
                                        <!-- {{ serviceSold(client.sold) }} -->
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

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
    Vue.component('v-select', VueSelect.VueSelect);
    new Vue({
        el: '#clients',
        data() {
            return {
                client: {
                    dateFrom: moment().format('YYYY-MM-DD'),
                    dateTo: moment().format('YYYY-MM-DD'),
                    userId: '<?php echo $this->session->userdata('userid') ?>',
                },
                selectedMember: null,
                members: [],
                clients: [],
                service: {},
                showDiv: false,
                columns: [{
                        label: 'Date',
                        field: 'add_time',
                        align: 'center',
                        filterable: false
                    },
                    {
                        label: 'Client ID',
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
                        label: 'Source',
                        field: 'source',
                        align: 'center'
                    },
                    {
                        label: 'Requirement',
                        field: 'requirement',
                        align: 'center',
                        filterable: false
                    },
                    {
                        label: 'Off. Name',
                        field: 'officer',
                        align: 'center',
                        filterable: false
                    },
                    {
                        label: 'Action',
                        align: 'center',
                        filterable: false,
                        headerClass: 'action'
                    }
                ],
                page: 1,
                per_page: 50,
                filter: ''
            }
        },

        filters: {
            dateOnly(datetime, format) {
                return moment(datetime).format(format);
            }
        },

        created() {
            this.getMenbers();
        },

        methods: {
            getMenbers() {
                axios.get('/get-team-member').then(res => {
                    this.members = res.data;
                })
            },

            searchClient() {
                let url = '/get-client';
                if (this.selectedMember != null) {
                    this.client.userId = this.selectedMember.id;
                }

                axios.post(url, this.client).then(res => {
                    this.clients = res.data.filter((item) => {
                        return item.sold == '';
                    });
                })

                this.showDiv = true;
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
        margin: 2px 0px;
        white-space: nowrap;
        position: absolute;
        left: 0px;
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
        border-collapse: collapse;
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

    .record-table th {
        text-align: center;
    }

    [v-cloak] {
        display: none;
    }
</style>