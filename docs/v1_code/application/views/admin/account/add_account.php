<style>
#accountForm select {
    padding: 0 !important;
}

#accountsTable .button {
    width: 25px;
    height: 25px;
    border: none;
    color: white;
}

#accountsTable .edit {
    background-color: #7bb1e0;
}

#accountsTable .delete {
    background-color: #ff6666;
}

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
</style>

<div id="accounts">
    <div class="widget-box">
        <div class="widget-header">
            <h4 class="widget-title">Account Information</h4>
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
                <form id="accountForm" class="form-horizontal" @submit.prevent="saveAccount">
                    <div class="row">
                        <div class="col-md-6 col-md-offset-1">
                            <div class="form-group">
                                <label class="control-label col-md-4">Account No</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="account.Acc_Code" required
                                        readonly>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-4">Account Holder Name</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="account.Acc_Name" required>
                                </div>
                            </div>

                            <div class="form-group" style="display:none;">
                                <label class="control-label col-md-4">Account Type</label>
                                <div class="col-md-8">
                                    <select class="form-control" v-model="account.Acc_Tr_Type">
                                        <option value="">Select Account Type</option>
                                        <option value="In Cash">Cash In</option>
                                        <option value="Out Cash">Cash Out</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-4">Designation</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="account.Acc_designation" required>
                                </div>
                            </div>


                            <div class="form-group">
                                <label class="control-label col-md-4">Contact Number</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="account.Acc_contact_number"
                                        required>
                                </div>
                            </div>

                            <div class="form-group clearfix">
                                <label class="control-label col-md-4">Client_Id</label>
                                <div class="col-md-8">
                                    <v-select v-bind:options="clients" label="client_id" v-model="selectedClient">
                                    </v-select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-4">Agreement Number</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="account.Acc_agreement_number"
                                        required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-4">Type of Work</label>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" v-model="account.Acc_type_work" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-4">Description</label>
                                <div class="col-md-8">
                                    <textarea class="form-control" v-model="account.Acc_description"></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-8 col-md-offset-4">
                                    <input type="submit" value="Save" class="btn btn-success">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="widget-box">
        <div class="widget-header">
            <h4 class="widget-title">Account List</h4>
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
                        <div id="accountsTable" class="table-responsive">
                            <datatable :columns="columns" :data="accounts" :filter-by="filter">
                                <template scope="{ row }">
                                    <tr>
                                        <td>{{ row.Acc_Code }}</td>
                                        <td>{{ row.Acc_Name }}</td>
                                        <td>{{ row.client_id }}</td>
                                        <td>{{ row.Acc_designation }}</td>
                                        <td>{{ row.Acc_type_work }}</td>
                                        <td>{{ row.Acc_agreement_number }}</td>
                                        <td>{{ row.Acc_contact_number }}</td>
                                        <td>{{ row.Acc_description }}</td>
                                        <td>
                                            <?php if ($this->session->userdata('accountType') != 'u') {?>
                                            <button class="button edit" @click="editAccount(row)">
                                                <i class="fa fa-pencil"></i>
                                            </button>
                                            <button class="button delete" @click="deleteAccount(row.Acc_SlNo)">
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

<script>
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#accounts',
    data() {
        return {
            account: {
                Acc_SlNo: null,
                Acc_Code: '<?php echo $accountCode; ?>',
                Acc_Tr_Type: '',
                Acc_Name: '',
                Acc_description: '',
                Acc_designation: '',
                Acc_type_work: '',
                Acc_agreement_number: '',
                client_id: '',
                Acc_contact_number: '',
            },
            accounts: [],

            clients: [],
            selectedClient: null,

            columns: [{
                    label: 'Account Id',
                    field: 'Acc_Code',
                    align: 'center'
                },
                {
                    label: 'Account Name',
                    field: 'Acc_Name',
                    align: 'center'
                },
                {
                    label: 'Client Id',
                    field: 'client_id',
                    align: 'center'
                },
                {
                    label: 'Designation',
                    field: 'Acc_designation',
                    align: 'center'
                },
                {
                    label: 'Type Work',
                    field: 'Acc_type_work',
                    align: 'center'
                },
                {
                    label: 'Agreement Number',
                    field: 'Acc_agreement_number',
                    align: 'center'
                },
                {
                    label: 'Contact Number',
                    field: 'Acc_contact_number',
                    align: 'center'
                },
                {
                    label: 'Description',
                    field: 'Acc_description',
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
        this.getClient();
    },
    methods: {

        getAccounts() {
            axios.get('/get_accounts').then(res => {
                this.accounts = res.data;
            })
        },

        getClient() {
            axios.get('/get-client').then(res => {
                this.clients = res.data.filter((e) => {
                    return e.sold != '';
                });
            })
        },

        saveAccount() {

            this.account.client_id = this.selectedClient.client_id;

            let url = '/add_account';
            if (this.account.Acc_SlNo != null) {
                url = '/update_account';
            }
            axios.post(url, this.account).then(res => {
                    let r = res.data;
                    alert(r.message);
                    if (r.success) {
                        this.resetForm();
                        this.account.Acc_Code = r.newAccountCode;
                        this.getAccounts();
                    }
                })
                .catch(error => {
                    if (error.response) {
                        alert(`${error.response.status}, ${error.response.statusText}`);
                    }
                })
        },

        editAccount(account) {
            Object.keys(this.account).forEach(key => {
                this.account[key] = account[key];
            })

            this.selectedClient = {
                client_id: account.client_id
            }

        },

        deleteAccount(accountId) {
            let confirmation = confirm("Are you sure?");
            if (confirmation == false) {
                return;
            }
            axios.post('/delete_account', {
                    accountId: accountId
                })
                .then(res => {
                    let r = res.data;
                    alert(r.message);

                    if (r.success) {
                        this.getAccounts();
                    }
                })
                .catch(error => {
                    if (error.response) {
                        alert(`${error.response.status}, ${error.response.statusText}`);
                    }
                })
        },

        resetForm() {
            this.account = {
                Acc_SlNo: null,
                Acc_Tr_Type: '',
                Acc_Name: '',
                Acc_description: ''
            }
            this.selectedClient = null;
        }
    }

})
</script>
