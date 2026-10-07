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
.addButton {
		background-color:green;
		border: 1px solid #ccc;
		border-radius: 7px;
		color:#fff;
		padding: 5px 10px;
		margin-top: 20px;
	}

	.updateButton {
		background-color:#d35400;
		border: 1px solid #ccc;
		border-radius: 7px;
		color:#fff;
		padding: 5px 10px;
		margin-top: 20px;
	}

	.DeleteButton {
		background-color:red;
		border: 1px solid #ccc;
		border-radius: 7px;
		color:#fff;
		padding: 5px 10px;
		margin-top: 20px;
	}
</style>
<div id="cashTransaction">
    <div class="row" style="border-bottom: 1px solid #ccc;padding-bottom: 15px;margin-bottom: 15px;">
        <div class="col-md-12">
            <form @submit.prevent="addTransaction">
                <div class="row">
                    <div class="col-md-5 col-md-offset-1">
                        <div class="form-group">
                            <label class="col-md-4 control-label">Transaction Id</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="text" class="form-control" v-model="transaction.Tr_Id" @keyup="getCashTransactionData($event)" readonly >
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
                            <label class="col-md-4 control-label">Bill Number</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="text" class="form-control" v-model="transaction.bill_number">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label">Receipt No</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <input type="text" class="form-control" v-model="transaction.receipt_no">
                            </div>
                        </div>

                        <div class="form-group" style="display:none">
                            <label class="col-md-4 control-label">Transaction Type</label>
                            <label class="col-md-1">:</label>
                            <div class="col-md-7">
                                <select class="form-control" v-model="transaction.Tr_Type"
                                    @change="onChangeTransactionType">
                                    <option value=""></option>
                                    <option value="In Cash">Cash Receive</option>
                                    <option value="Out Cash">Cash Payment</option>
                                </select>
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
                                <a href="/account" target="_blank" class="add-button"><i class="fa fa-plus"></i></a>
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
                                <a href="/account" target="_blank" class="add-button"><i class="fa fa-plus"></i></a>
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
                                    v-model="transaction.bill_amount">
                            </div>
                        </div>

                        <!-- <div class="form-group">
                            <div class="col-md-7 col-md-offset-5">
                                <input type="submit" class="btn btn-success btn-sm" value="Save">
                                <input type="button" class="btn btn-danger btn-sm" value="Cancel" @click="resetForm">
                            </div>
                        </div> -->

                    </div>
                </div>
            </form>
        </div>
    </div>

	
	<div class="row"> 
	<form v-on:submit.prevent="addToVisit">
		<div class="col-md-1"> 
			<label for="">Item Code</label>
			<div>
				<input type="text" class="form-control" v-model="vendorDetailsCart.item_code">
			</div>
		</div>
		<div class="col-md-1"> 
			<label for="">MB No</label>
			<div>
				<input type="text" class="form-control" v-model="vendorDetailsCart.mb_page_no">
			</div>
		</div>
		<div class="col-md-2"> 
			<label for="">Floor/Location</label>
			<div>
				<input type="text" class="form-control" v-model="vendorDetailsCart.visit_location">
			</div>
		</div>
		

		<div class="col-md-2"> 
			<label for="">Description</label>
			<div>
				<input type="text" class="form-control" v-model="vendorDetailsCart.visit_description">
			</div>
		</div>
		<div class="col-md-1"> 
			<label for="">Unit</label>
			<div>
				<input type="text" class="form-control" v-model="vendorDetailsCart.vendor_unit">
			</div>
		</div>

		<div class="col-md-2"> 
			<label for="">Estimated Quantity</label>
			<div>
				<input type="text" class="form-control" v-model="vendorDetailsCart.estimated_quantity">
			</div>
		</div>
		<div class="col-md-2"> 
			<label for="">Rate</label>
			<div>
				<input type="text" class="form-control" v-model="vendorDetailsCart.vendor_rate">
			</div>
		</div>

		
		<div class="col-md-1"> 
			<div>
				<button type="submit" class="addButton"> Add + </button>
			</div>
		</div>

		</form>
</div> 

			<div class="vist-details" style="margin-top:25px;">
				<div class="row" v-for="(visit, sl) in vendorCart" style="margin-bottom: 15px;">

					<div class="col-md-1"> 
						<label for="">Item Code</label>
						<div>
							<input type="text" class="form-control" v-model="visit.item_code">
						</div>
					</div>
					<div class="col-md-1"> 
						<label for="">MB No</label>
						<div>
							<input type="text" class="form-control" v-model="visit.mb_page_no">
						</div>
					</div>
					<div class="col-md-2"> 
						<label for="">Floor/Location</label>
						<div>
							<input type="text" class="form-control" v-model="visit.visit_location">
						</div>
					</div>
					
					<div class="col-md-2"> 
						<label for="">Description</label>
						<div>
							<input type="text" class="form-control" v-model="visit.visit_description">
						</div>
					</div>
					<div class="col-md-1"> 
						<label for="">Unit</label>
						<div>
							<input type="text" class="form-control" v-model="visit.vendor_unit">
						</div>
					</div>

					<div class="col-md-2"> 
						<label for="">Estimated Quantity</label>
						<div>
							<input type="text" class="form-control" v-model="visit.estimated_quantity">
						</div>
					</div>
					<div class="col-md-2"> 
						<label for="">Rate</label>
						<div>
							<input type="text" class="form-control" v-model="visit.vendor_rate">
						</div>
					</div>

					<div class="col-md-1"> 
						<div>
							<button type="submit" class="DeleteButton" v-on:click="removeItem(sl)"> Delete </button>
						</div>
					</div>

				    </div>
			</div>


			<div class="row">
				<div class="col-md-12">
										
					<div class="form-group clearfix">
						<div class="col-md-7 col-md-offset-5">
							<input type="submit" :class="transaction.Tr_SlNo == 0 ? 'addButton' : 'updateButton'" :value="transaction.Tr_SlNo != 0 ? 'Update' : 'Save' " v-on:click="addTransaction">
						</div>
					</div>
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
                            <td>{{ row.Tr_Id }}</td>
                            <td>{{ row.voucher_no }}</td>
                            <td>{{ row.receipt_no }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.project_name }}</td>
                            <td>{{ row.Tr_date }}</td>
                            <td>{{ row.Tr_Description }}</td>
                            <td>{{ row.bill_amount }}</td>
                            <td>{{ row.AddBy }}</td>
                            <td>
                                <?php if ($this->session->userdata('accountType') != 'u') {?>
                                <button type="button" class="button edit" @click="editTransaction(row)">
                                    <i class="fa fa-pencil"></i>
                                </button>
                                <button type="button" class="button" @click="deleteTransaction(row.Tr_SlNo)">
                                    <i class="fa fa-trash"></i>
                                </button>
                                <?php }?>
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
                Tr_Id: null,
                Tr_date: moment().format('YYYY-MM-DD'),
                voucher_no: '',
                receipt_no: '',
                vendor_id: null,
                project_id: null,
                Tr_Description: '',
                bill_number: '',
                bill_amount: 0,
            },
            transactions: [],
            employees: [],
            vendors:[],
            selectedEmployee: null,
            projects: [],
            selectedProject: null,
            accounts: [],
            testData : [],
            selectedAccount: null,
            selectedVendor : null,
			vendorDetailsCart : {
				        item_code: '',
						mb_page_no: '',
						visit_location: '',
						visit_description: '',
						vendor_unit: '',
						estimated_quantity: '',
						vendor_rate: ''
				},
			vendorCart: [],


            userType: '<?php echo $this->session->userdata('accountType'); ?>',

            columns: [{
                    label: 'Transaction Id',
                    field: 'Tr_Id',
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
                    label: 'Vendor Name',
                    field: 'name',
                    align: 'center'
                },
                {
                    label: 'Project Name',
                    field: 'project_name',
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
                    label: 'Bill Amount',
                    field: 'bill_amount',
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
        this.getTransactionCode();
        this.getAccounts();
        this.getTransactions();
        this.getVendors();
        this.getProjects();
    },
    methods: {

        getCashTransactionData (event) 
        {
            if(event.key === 'Enter' || event.key === ' ')
            {
                let formData = {
                    tr_id: this.transaction.Tr_Id
                }
                axios.post('/get_cash_transactions', formData).then(res => {
                this.testData = res.data[0];
                
                this.transaction = {
                voucher_no: this.testData.voucher_no,
                receipt_no: this.testData.receipt_no,
                bill_amount: this.testData.In_Amount,
                Tr_Id: this.testData.Tr_Id,
                Tr_SlNo : 0
                }
            })
            }
        },

        getTransactionCode() {
            axios.get('/get_vendor_expense_transaction_code').then(res => {
                this.transaction.Tr_Id = res.data;
            })
        },

        getProjects()
        {
            axios.get('/get-projects').then(res => {
                this.projects = res.data;
            })
        },

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

        onChangeTransactionType() {
            this.transaction.In_Amount = '';
            this.transaction.Out_Amount = '';

        },

        getTransactions() {
            let data = {
                dateFrom: this.transaction.Tr_date,
                dateTo: this.transaction.Tr_date
            }

            axios.post('/get_vendor_bill', data).then(res => {
                this.transactions = res.data;
            })
        },
		addToVisit ()
			{
				let visit = {
					item_code: this.vendorDetailsCart.item_code,
					mb_page_no: this.vendorDetailsCart.mb_page_no,
					visit_location: this.vendorDetailsCart.visit_location,
					visit_description: this.vendorDetailsCart.visit_description,
					vendor_unit: this.vendorDetailsCart.vendor_unit,
					estimated_quantity: this.vendorDetailsCart.estimated_quantity,
					vendor_rate: this.vendorDetailsCart.vendor_rate,
				}
				this.vendorCart.unshift(visit);
				this.clearvendorCart();
			},
		removeItem(ind)
			{
				this.vendorCart.splice(ind, 1);
			},

        addTransaction() {

            if(this.selectedVendor == null)
            {
                alert("Please Select Vendor");
                return;
            }


            if(this.selectedProject == null)
            {
                alert("Please Select Project");
                return;
            }

            this.transaction.vendor_id = this.selectedVendor.id;
            this.transaction.project_id = this.selectedProject.id;

            let url = '/add_vendor_bill_entry';
            if (this.transaction.Tr_SlNo != 0) 
            {
               url = '/update_vendor_bill_entry';
            }
			let vendorData = {
					project_visit: this.transaction,
					cart: this.vendorCart
				}
            axios.post(url, vendorData).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.resetForm();
                    this.getTransactions();
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

            this.selectedProject = {
                id: transaction.project_id,
                name: transaction.project_name
            }
			transaction.details.forEach((item) => {
					let visit = {
					item_code: item.item_code,
					mb_page_no: item.mb_page_no,
					visit_location: item.visit_location,
					visit_description: item.visit_description,
					vendor_unit: item.vendor_unit,
					estimated_quantity: item.estimated_quantity,
					vendor_rate: item.vendor_rate,
				}
				this.vendorCart.unshift(visit);
				})
        },
        deleteTransaction(transactionId) {
            axios.post('/delete_vendor_bill_entry', {
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
            this.transaction = {
                Tr_SlNo: this.getTransactionCode(),
                Tr_Id: null,
                Tr_date: moment().format('YYYY-MM-DD'),
                voucher_no: '',
                receipt_no: '',
                Acc_SlID: null,
                Tr_Description: '',
                bill_number: '',
                bill_amount: 0,
            },
			this.vendorCart = [];
            
            this.selectedVendor= null;
            this.selectedProject = null;

        },

		clearvendorCart()
			{
				this.vendorDetailsCart = {
						item_code: '',
						mb_page_no: '',
						visit_location: '',
						visit_description: '',
						vendor_unit: '',
						estimated_quantity: '',
						vendor_rate: ''
				}
			}
    }
})
</script>
