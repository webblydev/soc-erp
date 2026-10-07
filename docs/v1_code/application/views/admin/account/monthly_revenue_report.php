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
                    <label> Client Select </label>
                    <v-select v-bind:options="clients" v-model="selectedClient" label="display_name"
                    v-if="clients.length > 0"></v-select>
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
            <div class="table-responsive" id="printContent">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="text-align:center">Sl No</th>
                            <th style="text-align:center">Client Id</th>
                            <th style="text-align:center">Name of Client</th>
                            <th style="text-align:center">Project Id</th>
                            <th style="text-align:center">Project Name</th>
                            <th style="text-align:center">Client Address</th>
                            <th style="text-align:center">Task Entry No</th>
                            <th style="text-align:center">Total Deed Amount</th>
                            <th style="text-align:center">Start Date</th>
                            <th style="text-align:center">Handover Date</th>
                            <th style="text-align:center">Total Bill Amount</th>
                            <th style="text-align:center">Total Paid Amount</th>
                            <th style="text-align:center">Pay In This Month</th>
                            <th style="text-align:center">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, ind) in ledger" @key="ind">
                            <td> {{++ind}} </td>
                            <td style="text-align:left;">{{  row.client_id }}</td>
                            <td style="text-align:right;">{{ row.client_name }}</td>
                            <td style="text-align:right;">{{ row.project_id }}</td>
                            <td style="text-align:right;">{{ row.project_name }}</td>
                            <td style="text-align:left;">{{  row.client_address }}</td>
                            <td style="text-align:right;">{{ row.file_number }}</td>
                            <td style="text-align:right;">{{ parseFloat(row.bill_amount).toFixed(2) }}</td>
                            <td style="text-align:right;">{{ row.entry_date }}</td>
                            <td style="text-align:left;">{{  row.delivery_date }}</td>
                            <td style="text-align:right;">{{ parseFloat(row.bill_amount).toFixed(2) }}</td>
                            <td style="text-align:right;">{{ (parseFloat(row.collect_amount) + parseFloat(row.total_client_amount)).toFixed(2) }}</td>
                            <td style="text-align:right;">{{  parseFloat(row.this_month_paid).toFixed(2) }}</td>
                            <td style="text-align:left;">{{  row.task_detail }}</td>
                        </tr>
                    </tbody>
                    <tbody v-if="ledger.length == 0">
                        <tr>
                            <td colspan="14" style="font-size:16px; font-weight:bold;">No Records Found!</td>
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
            bill_amount : 0.00,
            showTable : false,
            clients : [],
            selectedClient: null,
        }
    },
    created ()
        {
            this.getClients();
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
                dateFrom : this.filter.dateFrom,
                dateTo : this.filter.dateTo,
                clientId: this.selectedClient != null ? this.selectedClient.id : null
            }
            axios.post('/get_monthly_revenue_report', data)
                .then(res => {
                    this.ledger = res.data;
                });
                this.showTable = true;
        },

        resetData() {
            this.transactions = [];
        },

        getClients() {
            axios.post('/get-client').then(res => {
                this.clients = res.data;
            })
        },

        async print() {
            let dateText = "";
            if (this.filter.dateFrom != null && this.filter.dateTo != null) {
                dateText =
                    `Statement from <strong>${this.filter.dateFrom}</strong>  to <strong>${this.filter.dateTo}</strong>`;
            }
            let printContent = `
                    <div class="container">
                        <h4 style="text-align:center">Monthly Revenue Report</h4 style="text-align:center">
                        <div class="row">
                            <div class="col-xs-6 col-xs-offset-6 text-right">
                                ${dateText}
                            </div>
                        </div>
                    </div>
                    <div class="container">
						<div class="row">
							<div class="col-xs-12">
								${document.querySelector('#printContent').innerHTML}
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

            printWindow.document.body.innerHTML += printContent;
            printWindow.focus();
            await new Promise(r => setTimeout(r, 1000));
            printWindow.print();
            await new Promise(resolve => setTimeout(resolve, 1000));
            printWindow.close();
        }


    }
})
</script>