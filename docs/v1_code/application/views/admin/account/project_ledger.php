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
</style>
<div id="bankTransactionReport">
    <div class="row" style="border-bottom: 1px solid #ccc;margin-bottom: 15px;">
        <div class="col-md-12">
            <form class="form-inline" @submit.prevent="getTransactions">

                <div class="form-group">
                    <label> Project Select </label>
                    <v-select v-bind:options="projects" v-model="selectedProject" label="name"
                        v-if="projects.length > 0"></v-select>
                </div>

                <div class="form-group">
                    <label> Date From </label>
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

    <div class="row" style="display:none;" v-bind:style="{display: showTable ? '' : 'none'}">
        <div class="col-md-12" style="margin-top:15px;margin-bottom:15px;">
            <a href="" @click.prevent="print"><i class="fa fa-print"></i> Print</a>
        </div>
        <div class="col-md-12">
            <div class="table-responsive" id="reportTable">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="text-align:center">Date</th>
                            <th style="text-align:center">Description</th>
                            <th style="text-align:center">Voucher/Client</th>
                            <th style="text-align:center">Bill</th>
                            <th style="text-align:center">Cash In</th>
                            <th style="text-align:center">Cash Out</th>
                            <th style="text-align:center">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td></td>
                            <td style="text-align:left;">Previous Balance</td>
                            <td colspan="4"></td>
                            <td style="text-align:right;">{{ parseFloat(bill_amount.total_bill_amount).toFixed(2) }}</td>
                        </tr>
                        <tr v-for="row in ledger" :key="row.id">
                            <td>{{ row.date }}</td>
                            <td style="text-align:left;">{{ row.description }}</td>
                            <td style="text-align:left;">{{ row.client_voucher }}</td>
                            <td style="text-align:left;">{{ row.bill }}</td>
                            <td style="text-align:right;">{{ parseFloat(row.in_amount).toFixed(2) }}</td>
                            <td style="text-align:right;">{{ parseFloat(row.out_amount).toFixed(2) }}</td>
                            <td style="text-align:right;">{{ parseFloat(row.balance).toFixed(2) }}</td>
                        </tr>
                    </tbody>
                    <tbody v-if="ledger.length == 0">
                        <tr>
                            <td colspan="7">No records found</td>
                        </tr>
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

<script>
    Vue.component('v-select', VueSelect.VueSelect);
    new Vue({
        el: '#bankTransactionReport',
        data() {
            return {
                transactions: [],
                ledger: [],
                filter: {
                    transactionType: '',
                    dateFrom: moment().format('YYYY-MM-DD'),
                    dateTo: moment().format('YYYY-MM-DD')
                },
                bill_amount: 0.00,
                showTable: false,
                projects: [],
                selectedProject: null,
            }
        },
        created() {
            this.getProjects();
        },
        methods: {
            // getAccounts() {
            //     axios.get('/get_bank_accounts')
            //         .then(res => {
            //             this.accounts = res.data;
            //         })
            //    },

            // getEmployees() {
            //     axios.post('/get-employees').then(res => {
            //         this.employees = res.data;
            //     })
            // },

            getTransactions() {

                let data = {
                    dateFrom: this.filter.dateFrom,
                    dateTo: this.filter.dateTo,
                    projectId: this.selectedProject != null ? this.selectedProject.id : null
                }

                axios.post('/get_project_ledger', data)
                    .then(res => {
                        this.ledger = res.data.ledger;
                        this.bill_amount = res.data.bill_amount;
                    });
                this.showTable = true;
            },

            resetData() {
                this.transactions = [];
            },

            getProjects() {
                axios.post('/get-projects').then(res => {
                    this.projects = res.data;
                })
            },

            async print() {
                let dateText = "";
                if (this.filter.dateFrom != null && this.filter.dateTo != null) {
                    dateText =
                        `Statement from <strong>${this.filter.dateFrom}</strong>  to <strong>${this.filter.dateTo}</strong>`;
                }
                let reportTable = `
                    <div class="container">
                        <h4 style="text-align:center">Cash Transaction Report</h4 style="text-align:center">
                        <div class="row">
                            <div class="col-xs-6 col-xs-offset-6 text-right">
                                ${dateText}
                            </div>
                        </div>
                    </div>
                    <div class="container">
						<div class="row">
							<div class="col-xs-12">
								${document.querySelector('#reportTable').innerHTML}
							</div>
						</div>
                    </div>
                `;

                let printWindow = window.open('', '', `width=${screen.width}, height=${screen.height}`);
                printWindow.document.write(`
                    <?php

                    $this->load->view('admin/reports/reportHeader.php');
?>
                `);

                printWindow.document.body.innerHTML += reportTable;
                printWindow.focus();
                await new Promise(r => setTimeout(r, 1000));
                printWindow.print();
                await new Promise(resolve => setTimeout(resolve, 1000));
                printWindow.close();
            }


        }
    })
</script>