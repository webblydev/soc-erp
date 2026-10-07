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
            <h4 class="widget-title">Client Transaction</h4>
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
                                <label for="" class="control-label col-md-4">Transaction Date</label>
                                <div class="col-md-8">
                                    <input type="date" class="form-control" v-model="transaction.transaction_date"
                                        required @change="getTransactions">
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
                                <label for="" class="control-label col-md-4">Task No</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="tasks" v-model="selectedTask"
                                        label="file_number" placeholder="Select Task">
                                    </v-select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Projects</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="projects" v-model="selectedProject"
                                        label="display_name" placeholder="Select Project">
                                    </v-select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Client Type</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="clientTypes" v-model="selectedClientType" label="name"
                                        placeholder="Select Client Type" @input="getClientTypeWise">
                                    </v-select>
                                </div>
                            </div>


                            <div class="form-group" style="display: none;"
                                :style="{display: selectedClientType != null ? '' : 'none'}">
                                <label for="" class="control-label col-md-4">Client</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="clients" v-model="selectedClient" label="display_name"
                                        @input="getCurrentBalance" placeholder="Select Client">
                                    </v-select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Payment Type</label>
                                <div class="col-md-8">
                                    <select class="form-control" v-model="transaction.transaction_type" required
                                        style="padding:0px;">
                                        <option value="">Select Type</option>
                                        <option value="deposit">Payment</option>
                                        <option value="withdraw">Receive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Tr. Number</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="cash_transactions" v-model="selectedCashTransaction" label="Tr_Id" @input="getCashTransactionData"
                                        placeholder="Select TR">
                                    </v-select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Voucher No</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="selectedCashTransaction.voucher_no" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="" class="control-label col-md-4">Amount</label>
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

                    <div class="col-md-2 col-md-offset-1 text-center" style="display:none;"
                        v-bind:style="{display: selectedClient == null || selectedClient.id == undefined ? 'none' : ''}">
                        <div
                            style="width: 100%;min-height: 150px;padding:15px 5px;background: #eeeeee;border: 1px solid #cdcdcd;margin-top: 15px;">
                            <i class="fa fa-dollar fa-2x"></i>
                            <h5>Current Due</h5>
                            <h3 style="color: green;">{{ accountBalance }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="widget-box">
        <div class="widget-header">
            <h4 class="widget-title">Client Transaction List</h4>
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
                                        <td>{{ row.transaction_date }}</td>
                                        <td>{{ row.client_name }}</td>
                                        <td>{{ row.name }}</td>
                                        <td v-if="row.transaction_type == 'withdraw' ">
                                            <span> Received</span>
                                        </td>
                                        <td v-else>
                                            <span> Payment</span>
                                        </td>
                                        <td>{{ row.file_number }}</td>
                                        <td>{{ row.display_name }}</td>
                                        <td>{{ row.note }}</td>
                                        <td>{{ row.amount }}</td>
                                        <td>{{ row.saved_by }}</td>
                                        <td>
                                            <?php if ($this->session->userdata('accountType') != 'u') { ?>
                                                <button class="button btn-info" @click="editTransaction(row)">
                                                    <i class="fa fa-pencil"></i>
                                                </button>
                                                <button class="button active-button" @click="removeTransaction(row)">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            <?php } ?>
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
                    transaction_id: 0,
                    transaction_date: moment().format('YYYY-MM-DD'),
                    transaction_type: '',
                    tr_number: '',
                    amount: '',
                    voucher_no: '',
                    note: '',
                    client_id: '',
                    task_id: '',
                    project_id: '',
                    client_type_id: ''
                },
                transactions: [],
                clientTypes: [],
                clients: [],
                selectedClient: null,

                selectedClientType: null,
                selectedCashTransaction: {
                    Tr_SlNo: '',
                    Tr_Id: '',
                    voucher_no: '',
                    Tr_Description: '',

                },
                cash_transactions: [],

                columns: [{
                        label: 'Transaction Date',
                        field: 'transaction_date',
                        align: 'center'
                    },
                    {
                        label: 'Client Name',
                        field: 'Client_name',
                        align: 'center'
                    },
                    {
                        label: 'Client Type',
                        field: 'name',
                        align: 'center'
                    },
                    {
                        label: 'Payment Type',
                        field: 'transaction_type',
                        align: 'center'
                    },
                    {
                        label: 'Task No',
                        field: 'file_number',
                        align: 'center'
                    },
                    {
                        label: 'Project Name',
                        field: 'display_name',
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
                selectedAccount: null,
                tasks: [],
                selectedTask: null,
                projects: [],
                selectedProject: null,
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
            this.getClientTypes();
            this.getCashTransactions();
            this.getTasks();
            this.getProjects();
        },
        methods: {

            getAccounts() {
                axios.get('/get_bank_accounts')
                    .then(res => {
                        this.accounts = res.data;
                    })
            },
            getTasks() {
                axios.post('/get-tasks')
                    .then(res => {
                        this.tasks = res.data;
                    })
            },
            getProjects() {
                axios.get("/get-projects").then(res => {
                    this.projects = res.data;
                })
            },

            getCashTransactionData() {
                if (!this.selectedCashTransaction || !this.selectedCashTransaction.Tr_Id) {
                    console.warn("No valid transaction selected");
                    return;
                }
                let formData = {
                    tr_id: this.selectedCashTransaction.Tr_Id
                };
                axios.post('/get_cash_transactions', formData).then(res => {
                    if (res.data && res.data.length > 0) {
                        this.testData = res.data[0];
                        this.transaction.amount = this.testData.In_Amount;
                        this.transaction.tr_number = this.testData.Tr_Id;
                        this.transaction.voucher_no = this.testData.voucher_no;
                        this.transaction.note = this.testData.Tr_Description || '';
                    } else {
                        console.warn("No data found for Tr_Id:", this.selectedCashTransaction.Tr_Id);
                    }
                });
            },


            getCashTransactions() {
                axios.post('/get_cash_transactions').then(res => {
                    this.cash_transactions = res.data;
                })
            },

            getClientTypeWise() {
                axios.post('/get-client', {
                    clientTypeId: this.selectedClientType.id
                }).then(res => {
                    this.clients = res.data.filter((item) => {
                        return item.sold != "";
                    });
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
                    dateFrom: this.transaction.transaction_date,
                    dateTo: this.transaction.transaction_date
                }
                axios.post('/get_client_transactions', data)
                    .then(res => {
                        this.transactions = res.data;
                    })
            },

            saveTransaction() {

                if (this.selectedClient == null) {
                    alert('Select an Client');
                    return;
                }

                if (this.selectedCashTransaction == null) {
                    alert("Please Select TR Number")
                    return;
                }

                this.transaction.task_id = this.selectedTask.id;
                this.transaction.project_id = this.selectedProject.id;
                this.transaction.client_id = this.selectedClient.id;
                this.transaction.client_type_id = this.selectedClientType.id;
                this.transaction.tr_number = this.selectedCashTransaction.Tr_Id;

                let url = '/add_client_transaction';
                if (this.transaction.transaction_id != 0) {
                    url = '/update_client_transaction';
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
                            window.location.href = "/project_transactions";
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

                this.selectedClientType = {
                    client_id: transaction.client_id,
                    name: transaction.name
                };

                this.selectedClient = {
                    id: transaction.client_id,
                    display_name: transaction.client_name
                };

                this.selectedTask = {
                    id: transaction.task_id,
                    file_number: transaction.file_number,
                };

                this.selectedProject = {
                    id: transaction.project_id,
                    display_name: transaction.display_name,
                };

                this.selectedCashTransaction = {
                    Tr_Id: transaction.tr_number,
                    voucher_no: transaction.voucher_no,
                    Tr_Description: transaction.note,
                };

                this.transaction.note = this.selectedCashTransaction.Tr_Description;
                this.getCurrentBalance();

            },

            removeTransaction(transaction) {
                let confirmation = confirm('Are you sure?');
                if (confirmation == false) {
                    return;
                }

                axios.post('/remove_client_transaction', transaction)
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
                if (this.selectedClient == null || this.selectedClient.id == undefined) {
                    return;
                }
                axios.post('/get_client_current_balance', {
                    ClientId: this.selectedClient.id
                }).then(res => {
                    if (res.data && res.data[0]) {
                        this.accountBalance = res.data[0].balance;
                    } else {
                        this.accountBalance = 0; // Handle case when no balance is returned
                    }
                }).catch(error => {
                    console.error("Error fetching client balance:", error);
                });
            },

            resetForm() {
                this.transaction.transaction_id = '';
                this.transaction.client_id = '';
                this.transaction.transaction_type = '';
                this.transaction.amount = '';
                this.transaction.voucher_no = '';
                this.transaction.note = '';

                this.selectedClient = null;
                this.selectedClientType = null;
                this.selectedCashTransaction = null;
                this.selectedProject = null;
                this.selectedTask = null;
            }
        }
    })
</script>