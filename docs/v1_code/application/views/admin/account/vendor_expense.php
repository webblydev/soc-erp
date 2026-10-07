<style>
    .v-select {
        margin-bottom: 5px;
    }

    .v-select.open .dropdown-toggle {
        border-bottom: 1px solid #ccc;
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

    #cashTransaction label {
        font-size: 13px;
    }

    #cashTransaction select {
        border-radius: 3px;
        padding: 0;
    }

    #cashTransaction .add-button {
        padding: 2.5px;
        width: 28px;
        background-color: #298db4;
        display: block;
        text-align: center;
        color: white;
    }

    #cashTransaction .add-button:hover {
        background-color: #41add6;
        color: white;
    }
</style>
<div id="cashTransaction">
    <div class="row" style="border-bottom: 1px solid #ccc;padding-bottom: 15px;margin-bottom: 15px;">
        <div class="col-md-12">
            <form @submit.prevent="addTransaction">
                <div class="row">
                    <div class="col-md-5 col-md-offset-1">


                        <div class="form-group">
                            <label class="col-md-4 control-label">Transaction Type</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <select class="form-control" required v-model="transaction.Tr_Type" @input="onChangeTransactionType">
                                    <option value="" disabled selected>Select Transaction Type</option>
                                    <option value="In Cash">Bill Receive</option>
                                    <option value="Out Cash">Bill Payment</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="control-label col-md-4">Tr. Number</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <v-select v-bind:options="cash_transactions" v-model="selectedCashTransaction" label="Tr_Id" @input="getCashTransactionData"
                                    placeholder="Select TR">
                                </v-select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-4 control-label">Voucher No</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="text" class="form-control" v-model="transaction.voucher_no">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label">Receipt No</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="text" class="form-control" v-model="transaction.receipt_no">
                            </div>
                        </div>


                        <div class="form-group">
                            <label class="col-md-4 control-label">Bill Number</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="text" class="form-control" v-model="transaction.bill_number">
                            </div>
                        </div>

                     

                        <div class="form-group">
                            <label class="col-md-4 control-label">Vendor</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-6 col-xs-11">
                                <select class="form-control" v-if="vendors.length == 0"></select>
                                <v-select v-bind:options="vendors" v-model="selectedVendor" label="name"
                                    v-if="vendors.length > 0"></v-select>
                            </div>
                            <div class="col-xs-1" style="padding-left:0;margin-left: -3px;">
                                <a href="/vendor" target="_blank" class="add-button"><i class="fa fa-plus"></i></a>
                            </div>
                        </div>


                        <div class="form-group">
                            <label class="col-md-4 control-label">Project</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-6 col-xs-11">
                                <select class="form-control" v-if="projects.length == 0"></select>
                                <v-select v-bind:options="projects" v-model="selectedProject" label="name"
                                    v-if="projects.length > 0"></v-select>
                            </div>
                            <div class="col-xs-1" style="padding-left:0;margin-left: -3px;">
                                <a href="/project_entry" target="_blank" class="add-button"><i class="fa fa-plus"></i></a>
                            </div>
                        </div>

                    </div>

                    <div class="col-md-5">
                        <div class="form-group">
                            <label class="col-md-4 control-label">Date</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="date" class="form-control" required v-model="transaction.Tr_date"
                                    @change="getTransactions" v-bind:disabled="userType == 'u' ? true : false">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-4 control-label">Description</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="text" class="form-control" v-model="transaction.Tr_Description">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-md-4 control-label">Amount</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="number" class="form-control" step="0.01" required
                                    v-model="transaction.In_Amount" style="display:none;"
                                    v-if="transaction.Tr_Type == 'In Cash'"
                                    v-bind:style="{display: transaction.Tr_Type == 'In Cash' ? '' : 'none'}">
                                <input type="number" class="form-control" step="0.01" required
                                    v-model="transaction.Out_Amount"
                                    v-if="transaction.Tr_Type == 'Out Cash' || transaction.Tr_Type == ''"
                                    v-bind:style="{display: transaction.Tr_Type == 'Out Cash' || transaction.Tr_Type == '' ? '' : 'none'}">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-md-7 col-md-offset-5">
                                <input type="submit" class="btn btn-success btn-sm" value="Save">
                                <input type="button" class="btn btn-danger btn-sm" value="Cancel" @click="resetForm">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12 form-inline">
            <div class="form-group">
                <label for="filter" class="sr-only">Filter</label>
                <input type="text" class="form-control" v-model="filter" placeholder="Filter">
            </div>
        </div>
        <div class="col-md-12">
            <div class="table-responsive">
                <datatable :columns="columns" :data="transactions" :filter-by="filter" style="margin-bottom: 5px;">
                    <template scope="{ row }">
                        <tr>
                            <td>{{ row.tr_number }}</td>
                            <td>{{ row.voucher_no }}</td>
                            <td>{{ row.receipt_no }}</td>
                            <td>{{ row.bill_number }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.Tr_date }}</td>
                            <td>{{ row.Tr_Description }}</td>
                            <td>{{ row.In_Amount }}</td>
                            <td>{{ row.Out_Amount }}</td>
                            <td>{{ row.AddBy }}</td>
                            <td>
                                <?php if ($this->session->userdata('accountType') != 'u') { ?>
                                    <button type="button" class="button edit" @click="editTransaction(row)">
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                    <button type="button" class="button" @click="deleteTransaction(row.Tr_SlNo)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                <?php } ?>
                            </td>
                        </tr>
                    </template>
                </datatable>
                <datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;">
                </datatable-pager>
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
        el: '#cashTransaction',
        data() {
            return {
                transaction: {
                    Tr_SlNo: 0,
                    Tr_date: moment().format('YYYY-MM-DD'),
                    Tr_Type: '',
                    tr_number: '',
                    Tr_account_Type: '',
                    voucher_no: '',
                    receipt_no: '',
                    vendor_id: null,
                    project_id: null,
                    Tr_Description: '',
                    bill_number: '',
                    In_Amount: '',
                    Out_Amount: ''
                },
                transactions: [],
                cash_transactions: [],
                selectedCashTransaction: null,
                vendors: [],
                selectedVendor: null,
                projects: [],
                selectedProject: null,
                accounts: [],
                selectedAccount: null,
                testData: [],
                userType: '<?php echo $this->session->userdata('accountType'); ?>',

                columns: [{
                        label: 'Transaction Id',
                        field: 'tr_number',
                        align: 'center'
                    },
                    {
                        label: 'Voucher No',
                        field: 'voucher_no',
                        align: 'center'
                    },
                    {
                        label: 'Receipt No',
                        field: 'receipt_no',
                        align: 'center'
                    },
                    {
                        label: 'Bill  Number',
                        field: 'bill_number',
                        align: 'center'
                    },
                    {
                        label: 'Vendor Name',
                        field: 'name',
                        align: 'center'
                    },
                   
                    {
                        label: 'Date',
                        field: 'Tr_date',
                        align: 'center'
                    },
                    {
                        label: 'Description',
                        field: 'Tr_Description',
                        align: 'center'
                    },
                    {
                        label: 'Received Amount',
                        field: 'In_Amount',
                        align: 'center'
                    },
                    {
                        label: 'Paid Amount',
                        field: 'Out_Amount',
                        align: 'center'
                    },
                    {
                        label: 'Saved By',
                        field: 'AddBy',
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
                filter: ''
            }
        },
        created() {
            this.getAccounts();
            this.getTransactions();
            this.getVendors();
            this.getCashTransactions();
            this.getProjects();
        },
        methods: {

            // getTransactionCode() {
            //     axios.get('/get_vendor_expense_transaction_code').then(res => {
            //         this.transaction.Tr_Id = res.data;
            //     })
            // },

            getVendors() {
                axios.get('/get-vendors').then(res => {
                    this.vendors = res.data;
                })
            },

            getAccounts() {
                axios.get('/get_accounts').then(res => {
                    this.accounts = res.data;
                })
            },
            getProjects() {
                axios.get('/get-projects').then(res => {
                    this.projects = res.data;
                })
            },

            getCashTransactions() {
                axios.post('/get_cash_transactions').then(res => {
                    this.cash_transactions = res.data;
                })
            },

            onChangeTransactionType() {
                this.transaction.Out_Amount = '';
                this.transaction.In_Amount = '';

            },

            getCashTransactionData() {
                let formData = {
                    tr_id: this.selectedCashTransaction.Tr_Id
                };
                axios.post('/get_cash_transactions', formData).then(res => {
                    this.testData = res.data[0];
                    this.transaction.voucher_no = this.testData.voucher_no;
                    this.transaction.receipt_no = this.testData.receipt_no;

                    if (this.testData.Tr_Type === 'In Cash') {
                        this.transaction.In_Amount = this.testData.In_Amount;
                        this.transaction.Out_Amount = '';
                    } else if (this.testData.Tr_Type === 'Out Cash') {
                        this.transaction.Out_Amount = this.testData.Out_Amount;
                        this.transaction.In_Amount = '';
                    }

                    this.transaction.tr_number = this.testData.Tr_Id;
                    this.transaction.Tr_Description = this.testData.Tr_Description;
                });
            },

            getTransactions() {
                let data = {
                    dateFrom: this.transaction.Tr_date,
                    dateTo: this.transaction.Tr_date
                }

                axios.post('/get_vendor_expense', data).then(res => {
                    this.transactions = res.data;
                })
            },

            addTransaction() {

                if (this.selectedVendor == null) {
                    alert("Please Select Vendor");
                    return;
                }

                if (this.selectedProject == null) {
                    alert("Please Select Project");
                    return;
                }

                this.transaction.vendor_id = this.selectedVendor.id;
                this.transaction.project_id = this.selectedProject.id;

                let url = '/add_vendor_expense';
                if (this.transaction.Tr_SlNo != 0) {
                    url = '/update_vendor_expense';
                }
                axios.post(url, this.transaction).then(res => {
                    let r = res.data;
                    alert(r.message);
                    if (r.success) {
                        this.resetForm();
                        this.getTransactions();
                        this.getAccounts();
                    }
                })
            },
            editTransaction(transaction) {

                let keys = Object.keys(this.transaction);
                keys.forEach(key => {
                    this.transaction[key] = transaction[key];
                })

                this.selectedVendor = {
                    id: transaction.vendor_id,
                    name: transaction.name
                }

                console.log(transaction);
                return;
                this.selectedProject = {
                    id: transaction.project_id,
                    name: transaction.project_name
                }

            },
            deleteTransaction(transactionId) {
                axios.post('/delete_cash_transaction', {
                    transactionId: transactionId
                }).then(res => {
                    let r = res.data;
                    alert(r.message);
                    if (r.success) {
                        this.getTransactions();
                    }
                })
            },
            resetForm() {
                this.transaction.Tr_SlNo = 0;
                this.transaction.tr_number = '';
                this.transaction.bill_number = '';
                this.transaction.Tr_account_Type = '';
                this.transaction.Acc_SlID = '';
                this.transaction.Tr_Description = '';
                this.transaction.In_Amount = '';
                this.transaction.Out_Amount = '';

                this.selectedAccount = null;
                this.selectedVendor = null;
                this.selectedProject = null;
                this.selectedCashTransaction = null;

            }
        
        }
    })
</script>