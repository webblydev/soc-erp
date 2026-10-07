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
                    <label> Project Type </label>
                    <v-select v-bind:options="projectTypes" v-model="selectedProjectType" label="name"
                        placeholder="Select Project Type" @input="getClientTypeWise">
                    </v-select>
                </div>

                <div class="form-group" style="display: none;"
                    :style="{display: selectedProjectType != null ? '' : 'none'}">
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

    <div class="row" style="display:none;" v-bind:style="{display: transactions.length > 0 ? '' : 'none'}">
        <div class="col-md-12" style="margin-top:15px;margin-bottom:15px;">
            <a href="" @click.prevent="print"><i class="fa fa-print"></i> Print</a>
        </div>
        <div class="col-md-12">
            <div class="table-responsive" id="printContent">
                <table class="table table-bordered table-condensed" id="transactionsTable">
                    <thead>
                        <tr>
                            <th>Sl</th>
                            <!-- <th>Description</th> -->
                            <th> Date</th>
                            <th>Voucher No</th>
                            <th>Name of Supplier Vendor/Contractor </th>
                            <th>Project Type</th>
                            <th>Project Name</th>
                            <!-- <th>Note</th> -->
                            <th>Invoice No</th>
                            <th>Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="(transaction, sl) in transactions">
                            <td style="text-align:right">{{ sl + 1}}</td>
                            <!-- <td style="text-align:left;">{{ transaction.description }}</td> -->
                            <td>{{ transaction.expense_date }}</td>
                            <td>{{ transaction.voucher_number }}</td>
                            <td>{{ transaction.assign_to }}</td>
                            <td>{{ transaction.project_type }}</td>
                            <td>{{ transaction.project_name }}</td>
                            <!-- <td>{{ transaction.note }}</td> -->
                            <td>{{ transaction.tr_number }}</td>
                            <td style="text-align:right">{{ transaction.amount }}</td>
                        </tr>
                    </tbody>

                    <tfoot>
                        <tr style="font-weight:bold;">
                            <td colspan="6" style="text-align:right;">Total &nbsp;</td>
                            <td style="text-align:right;">
                                {{ transactions.reduce((prev,curr) => prev + parseFloat(curr.amount), 0) }}
                            </td>
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
            projectTypes: [],
            selectedProject: null,
            projects: [],
            selectedProjectType: null,
            selectedClient: null,
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
        this.getProjectTypes();
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

        getClientTypeWise() {

            axios.post('/get-projects', {
                projectTypeId: this.selectedProjectType.id
            }).then(res => {
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

            axios.post('/get_all_project_expense', this.filter)
                .then(res => {
                    this.transactions = res.data['transactions'];
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
                        <h4 style="text-align:center">Project Expense Report</h4 style="text-align:center">
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
