<style>
    .v-select {
        margin-bottom: 5px;
        float: right;
        min-width: 150px;
        margin-left: 5px;
    }

    .v-select .dropdown-toggle {
        padding: 0px;
        height: 23px;
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

    #bankTransactionReport label {
        font-size: 13px;
    }

    #bankTransactionReport select {
        border-radius: 3px;
        padding: 0px;
    }

    #bankTransactionReport .form-group {
        margin-right: 5px;
    }

    #bankTransactionReport .search-button {
        margin-top: -6px;
    }

    #transactionsTable th {
        text-align: center;
    }

    .v-select {
        margin-top: -2.5px;
        float: right;
        min-width: 180px;
        margin-left: 5px;
    }

    .v-select .dropdown-toggle {
        padding: 0px;
        height: 25px;
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

    #searchForm select {
        padding: 0;
        border-radius: 4px;
    }

    #searchForm .form-group {
        margin-right: 5px;
    }

    #searchForm * {
        font-size: 13px;
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

    @media print {

        .record-table th,
        .record-table td {
            border: 1px solid #454545 !important;
        }
    }
</style>
<div id="bankTransactionReport">
    <div class="row" style="border-bottom: 1px solid #ccc;margin-bottom: 15px;">
        <div class="col-md-12">
            <form class="form-inline" @submit.prevent="getTransactions">

                <!-- <div class="form-group">
                    <label> Project Type </label>
                    <v-select v-bind:options="projectTypes" v-model="selectedProjectType" label="name"
                        placeholder="Select Project Type" @input="getClientTypeWise">
                    </v-select>
                </div> -->

                <div class="form-group">
                    <label>Project</label>
                    <v-select v-bind:options="projects" v-model="selectedProject" label="name"
                        placeholder="Select Project">
                    </v-select>
                </div>

                <div class="form-group">
                    <label>Transaction Type</label>
                    <select class="form-control" v-model="filter.transactionType" @change="resetData">
                        <option value="">All</option>
                        <option value="deposit">Payment</option>
                        <option value="withdraw">Received</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" class="form-control" v-model="filter.dateFrom" @change="resetData">
                </div>

                <div class="form-group">
                    <label>to</label>
                    <input type="date" class="form-control" v-model="filter.dateTo" @change="resetData">
                </div>

                <div class="form-group">
                    <input type="submit" value="search" class="search-button">
                </div>
            </form>
        </div>
    </div>

    <div class="row" style="display:none" :style="{display: showTable ? '' : 'none'}">

        <div class="col-md-6" style="margin-top:15px;margin-bottom:15px;">
            <a href="" @click.prevent="print"><i class="fa fa-print"></i> Print</a>
        </div>

        <div class="col-xs-6" style="margin-bottom: 10px; text-align:right;">
            <a class="dataExport btn btn-sm btn-primary" @click="download()" data-type="excel">Export Excel</a>
        </div>

        <div class="col-md-12">
            <div class="table-responsive" id="dataTable">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>Construction Id</th>
                            <th>Inspaction Date</th>
                            <th>Project Name</th>
                            <th>Project Eng Name </th>
                            <th>Constructor Name</th>
                            <th> permitee_name </th>
                            <th>Visit Location</th>
                            <th>Visit Description</th>
                            <th>Visit Finding</th>
                            <th>Visit Regarding</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="visit in project_visit.project_visit">
                            <tr>
                                <td>{{ visit.construction_id }}</td>
                                <td>{{ visit.inspection_date }}</td>
                                <td>{{ visit.name }}</td>
                                <td>{{ visit.project_eng_name }}</td>
                                <td>{{ visit.constructor_name }}</td>
                                <td>{{ visit.permitee_name }}</td>
                                <td style="text-align:right;" v-if="visit.details && visit.details.length > 0">{{ visit.details[0].visit_location }}</td>
                                <td style="text-align:center;" v-if="visit.details && visit.details.length > 0">{{ visit.details[0].visit_description }}</td>
                                <td style="text-align:right;" v-if="visit.details && visit.details.length > 0">{{ visit.details[0].visit_finding }}</td>
                                <td v-if="visit.details && visit.details.length > 0"> {{visit.details[0].visit_regarding }} </td>
                                <td style="text-align:center;">
                                    <a href="" class="btn btn-primary" title="Visit Invoice" v-bind:href="`/project_visit_invoice_print/${visit.Project_Visit_SlNo}`" target="_blank">Visit Invoice</a>
                            </tr>
                            <tr v-for="(product, sl) in visit.details.slice(1)" v-if="visit.details && visit.details.length > 1">
                                <td colspan="6" v-bind:rowspan="visit.details.length - 1" v-if="sl == 0"></td>
                                <td>{{ product.visit_location }}</td>
                                <td style="text-align:right;">{{ product.visit_description }}</td>
                                <td style="text-align:center;">{{ product.visit_finding }}</td>
                                <td style="text-align:right;">{{ product.visit_regarding }}</td>
                                <td> </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/tableexport.jquery.plugin/tableExport.min.js"></script>

<script>
    Vue.component('v-select', VueSelect.VueSelect);
    new Vue({
        el: '#bankTransactionReport',
        data() {
            return {
                accounts: [],
                selectedAccount: null,
                project_visit: [],
                filter: {
                    accountId: null,
                    transactionType: '',
                    dateFrom: moment().format('YYYY-MM-DD'),
                    dateTo: moment().format('YYYY-MM-DD')
                },
                clientTypes: [],
                clients: [],
                projectTypes: [],
                selectedProject: null,
                projects: [],
                selectedProjectType: null,
                selectedClient: null,
                selectedClientType: null,
                showTable: false,
            }
        },
        computed: {
            computedAccounts() {
                let accounts = this.accounts.filter(account => account.status == '1');
                return accounts.map(account => {
                    account.display_text = `${account.account_number} (${account.bank_name})`;
                    return account;
                })
            }
        },
        created() {
            this.getAccounts();
            this.getProjects();
        },
        methods: {
            getAccounts() {
                axios.get('/get_bank_accounts')
                    .then(res => {
                        this.accounts = res.data;
                    })
            },

            getProjectTypes() {

                axios.get('/get-types')
                    .then(res => {
                        this.projectTypes = res.data;
                    })
            },

            downloadPDF() {

                var doc = new jsPDF()

                doc.text('Hello world!', 10, 10)
                doc.save('a4.pdf')
            },

            getProjects() {

                axios.post('/get-projects').then(res => {
                    this.projects = res.data;
                })

            },

            getTransactions() {

                if (this.selectedProjectType != null) {
                    this.filter.projectTypeId = this.selectedProjectType.id;
                } else {
                    this.filter.ProjectTypeId = null;
                }

                this.filter.project_id = this.selectedProject != null && this.selectedProject.id != '' ?
                    this.selectedProject.id : null;

                axios.post('/get_project_visit', this.filter)
                    .then(res => {
                        this.project_visit = res.data;
                        this.showTable = true;
                    })
                    .catch(error => {
                        if (error.response) {
                            alert(`${error.response.status}, ${error.response.statusText}`);
                        }
                    })
            },

            resetData() {
                this.transactions = [];
            },

            async print() {
                let dateText = "";
                if (this.filter.dateFrom != null && this.filter.dateTo != null) {
                    dateText = `Statement from <strong>${this.filter.dateFrom}</strong> to <strong>${this.filter.dateTo}</strong>`;
                }

             
                let dataTable = `
                    <div class="container">
                        <h4 style="text-align:center">Project Visit Record</h4>
                        <div class="row">
                            <div class="col-xs-6 col-xs-offset-6 text-right">
                                ${dateText}
                            </div>
                        </div>
                    </div>
                    <div class="container">
                        <div class="row">
                            <div class="col-xs-12">
                                ${document.querySelector('#dataTable').innerHTML}
                            </div>
                        </div>
                    </div>
                `;

                let printWindow = window.open('', '', `width=${screen.width}, height=${screen.height}`);
                printWindow.document.write(`
        <?php $this->load->view('admin/reports/reportHeader.php'); ?>
                `);

                printWindow.document.head.innerHTML += `
                    <style>
                        .record-table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        .record-table th, .record-table td {
                            padding: 3px;
                            border: 1px solid #454545;
                        }
                        .record-table th {
                            text-align: center;
                        }
                        .record-table th:last-child,
                        .record-table td:last-child {
                            display: none;
                        }
                    </style>
                `;

                printWindow.document.body.innerHTML += dataTable;
                printWindow.focus();
                await new Promise(r => setTimeout(r, 1000));
                printWindow.print();
                await new Promise(resolve => setTimeout(resolve, 1000));
                printWindow.close();
            }


        }
    })



    function download() {
        var $j = jQuery.noConflict();
        $(document).ready(function() {
            $('#dataTable').tableExport();
        });
    }
</script>