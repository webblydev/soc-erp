<style>
.v-select {
    margin-bottom: 5px;
}

.v-select .dropdown-toggle {
    padding: 0px;
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

.button {
    width: 25px;
    height: 25px;
    border: none;
    color: white;
}

.active-button {
    background-color: rgb(252, 89, 89);
}

.transaction-deposit {
    background-color: #f0f4f0;
}

.transaction-withdraw {
    background-color: #fff4f4;
}
</style>

<div id="bankTransactions">
    <div class="widget-box">
        <div class="widget-header">
            <h4 class="widget-title">Project Expense</h4>
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
                    <div class="col-md-6 col-md-offset-1">
                        <form action="" class="form-horizontal" @submit.prevent="saveTransaction">
                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Expense Date</label>
                                <div class="col-md-8">
                                    <input type="date" class="form-control" v-model="transaction.expense_date" required
                                        @change="getTransactions">
                                </div>
                            </div>

                            <!-- <div class="form-group">
                                <label for="" class="control-label col-md-4">Account</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="filteredAccounts" v-model="selectedAccount"
                                        label="display_text" placeholder="Select account" @input="getBankBalance">
                                    </v-select>
                                </div>
                            </div> -->

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Project Type</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="projectTypes" v-model="selectedProjectType" label="name"
                                        placeholder="Select Client Type" @input="getClientTypeWise">
                                    </v-select>
                                </div>
                            </div>


                            <div class="form-group" style="display: none;"
                                :style="{display: selectedProjectType != null ? '' : 'none'}">
                                <label for="" class="control-label col-md-4">Project</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="projects" v-model="selectedProject" label="name"
                                     placeholder="Select Client">
                                    </v-select>
                                </div>
                            </div>

                            <div class="form-group" style="display: none;"
                                :style="{display: selectedProject != null ? '' : 'none'}">
                                <label for="" class="control-label col-md-4">Assigned To</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="transaction.assign_to" >
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Tr. Number</label>
                                <div class="col-md-8">
                                    <!-- <input type="text" class="form-control" v-model="transaction.tr_number" required> -->
                                    <v-select v-bind:options="cash_transactions" v-model="selectedCashTransaction" label="Tr_Id" @input="getCashTransactionData"
                                     placeholder="Select TR">
                                    </v-select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Voucher Number</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="transaction.voucher_number"
                                        required>
                                </div>
                            </div>


                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Expense Amount</label>
                                <div class="col-md-8">
                                    <input type="number" class="form-control" v-model="transaction.amount" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Note</label>
                                <div class="col-md-8">
                                    <textarea class="form-control" v-model="transaction.note"></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-8 col-md-offset-4">
                                    <input type="submit" value="Save Transaction"
                                        v-bind:disabled="onProgress ? true : false" class="btn btn-success btn-xs">
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="widget-box">
        <div class="widget-header">
            <h4 class="widget-title">Project Expense List</h4>
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
                    <div class="col-md-4">
                        <label for="filter" class="sr-only">Filter</label>
                        <input type="text" class="form-control" v-model="filter" placeholder="Filter">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <datatable :columns="columns" :data="transactions" :filter-by="filter">
                                <template scope="{ row }">
                                    <tr
                                        v-bind:class="[row.transaction_type == 'deposit' ? 'transaction-deposit' : 'transaction-withdraw']">
                                        <td>{{ row.expense_date }}</td>
                                        <td>{{ row.project_name }}</td>
                                        <td>{{ row.project_type }}</td>
                                        <td>{{ row.tr_number}}</td>
                                        <td> {{ row.voucher_number}}</td>
                                        <td>{{ row.assign_to }}</td>
                                        <td>{{ row.note }}</td>
                                        <td>{{ row.amount }}</td>
                                        <td>{{ row.saved_by }}</td>
                                        <td>
                                            <?php if ($this->session->userdata('accountType') != 'u') {?>
                                            <button class="button btn-info" @click="editTransaction(row)">
                                                <i class="fa fa-pencil"></i>
                                            </button>
                                            <button class="button active-button" @click="removeTransaction(row)">
                                                <i class="fa fa-trash"></i>
                                            </button>
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

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#bankTransactions',
    data() {
        return {
            transaction: {
                expense_id: 0,
                expense_date: moment().format('YYYY-MM-DD'),
                amount: '',
                assign_to: '',
                note: '',
                projectId: '',
                tr_number: '',
                voucher_number: '',
                project_type_id: ''
            },
            transactions: [],
            projectTypes: [],
            cash_transactions: [],
            selectedCashTransaction: null,
            selectedProjectType: null,
            projects: [],
            selectedProject: null,
            clients: [],
            selectedClient: null,
            selectedClientType: null,
            columns: [{
                    label: 'Expense Date',
                    field: 'transaction_date',
                    align: 'center'
                },
                {
                    label: 'Project Name',
                    field: 'project_name',
                    align: 'center'
                },
                {
                    label: 'Project Type',
                    field: 'name',
                    align: 'center'
                },
                {
                    label: 'Tr_Number',
                    field: 'tr_number',
                    align: 'center'
                },
                {
                    label: 'Voucher Number',
                    field: 'voucher_number',
                    align: 'center'
                },
                {
                    label: 'Assign_To',
                    field: 'assign_to',
                    align: 'center'
                },

                {
                    label: 'Note',
                    field: 'note',
                    align: 'center'
                },
                {
                    label: 'Amount',
                    field: 'amount',
                    align: 'center'
                },
                {
                    label: 'Saved By',
                    field: 'saved_by',
                    align: 'center'
                },
                {
                    label: 'Action',
                    align: 'center',
                    filterable: false
                }
            ],
            page: 1,
            per_page: 10,
            filter: '',
            accounts: [],
            testData:[],
            selectedAccount: null,
            accountBalance: null,
            onProgress: false
        }
    },
    computed: {
        filteredAccounts() {
            let accounts = this.accounts.filter(account => account.status == '1');
            return accounts.map(account => {
                account.display_text =
                    `${account.account_name} - ${account.account_number} (${account.bank_name})`;
                return account;
            })
        },
    },
    created() {
        this.getAccounts();
        this.getTransactions();
        this.getProjectTypes();
        this.getCashTransactions();
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

        getCashTransactionData () 
        {
            let formData = {
                tr_id: this.selectedCashTransaction.Tr_Id
            }
                axios.post('/get_cash_transactions', formData).then(res => {
                this.testData = res.data[0];
                this.transaction.voucher_number = this.testData.voucher_no;
                this.transaction.amount = this.testData.Tr_Type == 'In Cash' ?  this.testData.In_Amount: this.testData.Out_Amount;
                this.transaction.tr_number = this.testData.Tr_Id;
                this.transaction.note = this.testData.Tr_Description;
            })
            
        },

        getClientTypeWise() {

            axios.post('/get-projects', {
                projectTypeId: this.selectedProjectType.id
            }).then(res => {
                this.projects = res.data;
            })

        },

        getCashTransactions ()
        {
            axios.post('/get_cash_transactions').then(res => {
                this.cash_transactions = res.data;
            })
        },

        getClientTypes() {

            axios.get('/get-clientType')
                .then(res => {
                    this.clientTypes = res.data;
                })
        },

        getTransactions() {
            let data = {
                dateFrom: this.transaction.expense_date,
                dateTo: this.transaction.expense_date
            }
            axios.post('/get_project_expense', data)
                .then(res => {
                    this.transactions = res.data;
                })
        },

        saveTransaction() {

            if (this.selectedProject == null) {
                alert('Select an Project');
                return;
            }

            this.transaction.project_type_id = this.selectedProjectType.id;
            this.transaction.projectId = this.selectedProject.id;
            let url = '/add_project_expense';
            if (this.transaction.expense_id != 0) {
                url = '/update_project_expense';
            }

            this.onProgress = true;
            axios.post(url, this.transaction)
                .then(res => {
                    let r = res.data;
                    alert(r.message);
                    if (r.success) {
                        this.resetForm();
                        this.getTransactions();
                        this.onProgress = false;
                    }
                })
                .catch(error => {
                    if (error.response) {
                        alert(`${error.response.status}, ${error.response.statusText}`)
                    }
                })
        },

        editTransaction(transaction) {
            let keys = Object.keys(this.transaction);
            keys.forEach(key => this.transaction[key] = transaction[key]);

            this.selectedProjectType = {
                id: transaction.project_type_id,
                name: transaction.project_type
            }

            this.selectedEmployee = {
                id: transaction.assign_to,
                name: transaction.name
            }

            this.selectedProject = {
                name: transaction.project_name,

            }
            this.selectedCashTransaction = {
                Tr_Id: transaction.tr_number,
            }
        },

        removeTransaction(transaction) {
            let confirmation = confirm('Are you sure?');
            if (confirmation == false) {
                return;
            }

            axios.post('/remove_project_expense', transaction)
                .then(res => {
                    let r = res.data;
                    alert(r.message);
                    if (r.success) {
                        this.getTransactions();
                    }
                })
                .catch(error => {
                    if (error.response) {
                        alert(`${error.response.status}, ${error.response.statusText}`)
                    }
                })
        },

        getCurrentBalance() {

            if (this.selectedClientType == null || this.selectedClientType.id == undefined) {
                return;
            }

            axios.post('/get_client_current_balance', {
                ClientId: this.selectedClient.id
            }).then(res => {
                this.accountBalance = res.data[0].balance;
                console.log(this.accountBalance);
            })

        },

        resetForm() {
            this.transaction.transaction_id = '';
            this.transaction.client_id = '';
            this.transaction.transaction_type = '';
            this.transaction.amount = '';
            this.transaction.note = '';
            this.transaction.tr_number = '';
            this.transaction.voucher_number = '';

            this.selectedClient = null;
            this.selectedClientType = null;
            this.selectedCashTransaction = null;

        }
    }
})
</script>
