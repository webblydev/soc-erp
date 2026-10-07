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

                <!-- <div class="form-group">
                    <label> Client Type </label>
                    <v-select v-bind:options="clientTypes" v-model="selectedClientType" label="name"
                        placeholder="Select Client Type" @input="getClientTypeWise">
                    </v-select>
                </div> -->

                <div class="form-group">
                    <label>Vendor</label>
                    <v-select v-bind:options="employees" v-model="selectedEmployee" label="name"
                        placeholder="Select Vendor">
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

    <div class="row" style="display:none;" v-bind:style="{display: transactions.length > 0 ? '' : 'none'}">
        <div class="col-md-12" style="margin-top:15px;margin-bottom:15px;">
            <a href="" @click.prevent="print"><i class="fa fa-print"></i> Print</a>
        </div>
        <div class="col-md-12">
            <div class="table-responsive" id="printContent">
                <table class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>Transaction Id</th>
                            <th>Db.Voucher No</th>
                            <th>Money Receipt No</th>
                            <th>Tr. Type</th>
                            <th>Date:</th>
                            <th>Vendor Name</th>
                            <th>Bill Number</th>
                            <th>Description</th>
                            <th>Received Amount</th>
                            <th>Paid Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="transaction in transactions">
                            <td>{{ transaction.Tr_Id }}</td>
                            <td>{{ transaction.voucher_no }}</td>
                            <td>{{ transaction.receipt_no }}</td>
                            <td>
                                <span v-if="transaction.Tr_Type == 'In Cash'">Cash Received</span>
                                <span v-else>Cash Payment</span>
                            </td>
                            <td>{{ transaction.Tr_date }}</td>
                            <td>{{ transaction.name }}</td>
                            <td>{{ transaction.bill_number }}</td>
                            <td>{{ transaction.Tr_Description }}</td>
                            <td style="text-align:right;">{{ transaction.In_Amount }}</td>
                            <td style="text-align:right;">{{ transaction.Out_Amount }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="text-align:right;font-weight:bold;">Total</td>
                            <td style="text-align:right;font-weight:bold;">
                                {{ transactions.reduce((p, c) => { return p + parseFloat(c.In_Amount) }, 0) }}</td>
                            <td style="text-align:right;font-weight:bold;">
                                {{ transactions.reduce((p, c) => { return p + parseFloat(c.Out_Amount) }, 0) }}</td>
                        </tr>
                    </tfoot>
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
            accounts: [],
            selectedAccount: null,
            transactions: [],
            filter: {
                accountId: null,
                transactionType: '',
                dateFrom: moment().format('YYYY-MM-DD'),
                dateTo: moment().format('YYYY-MM-DD')
            },
            clientTypes: [],
            clients: [],
            employees: [],
            selectedClient: null,
            selectedEmployee: null,
            selectedClientType: null,
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
        this.getClientTypes();
        this.getEmployees();
    },
    methods: {
        getAccounts() {
            axios.get('/get_bank_accounts')
                .then(res => {
                    this.accounts = res.data;
                })
        },

        getClientTypes() {

            axios.get('/get-clientType')
                .then(res => {
                    this.clientTypes = res.data;
                })
        },

        getEmployees() {
            axios.post('/get-employees').then(res => {
                this.employees = res.data;
            })
        },


        getTransactions() {

            if (this.selectedEmployee != null) {
                this.filter.clientId = this.selectedEmployee.id;
            } else {
                this.filter.clientId = null;
            }

            axios.post('/get_all_vendor_expense_transactions', this.filter)
                .then(res => {
                    this.transactions = res.data;
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
                dateText =
                    `Statement from <strong>${this.filter.dateFrom}</strong>  to <strong>${this.filter.dateTo}</strong>`;
            }
            let printContent = `
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