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

.v-select{
		margin-top:-2.5px;
        float: right;
        min-width: 180px;
        margin-left: 5px;
	}
	.v-select .dropdown-toggle{
		padding: 0px;
        height: 25px;
	}
	.v-select input[type=search], .v-select input[type=search]:focus{
		margin: 0px;
	}
	.v-select .vs__selected-options{
		overflow: hidden;
		flex-wrap:nowrap;
	}
	.v-select .selected-tag{
		margin: 2px 0px;
		white-space: nowrap;
		position:absolute;
		left: 0px;
	}
	.v-select .vs__actions{
		margin-top:-5px;
	}
	.v-select .dropdown-menu{
		width: auto;
		overflow-y:auto;
	}
	#searchForm select{
		padding:0;
		border-radius: 4px;
	}
	#searchForm .form-group{
		margin-right: 5px;
	}
	#searchForm *{
		font-size: 13px;
	}
	.record-table{
		width: 100%;
		border-collapse: collapse;
	}
	.record-table thead{
		background-color: #0097df;
		color:white;
	}
	.record-table th, .record-table td{
		padding: 3px;
		border: 1px solid #454545;
	}
    .record-table th{
        text-align: center;
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
        <div class="col-md-12" style="margin-top:15px;margin-bottom:15px;">
            <a href="" @click.prevent="print"><i class="fa fa-print"></i> Print</a>
        </div>
        <div class="col-md-12">
            <div class="table-responsive" id="printContent">
                <!-- <table class="table table-bordered table-condensed" id="transactionsTable">
                    <thead>
                        <tr>
                            <th>Sl</th>
                            <th>Description</th>
                            <th>Expense Date</th>
                            <th>Project Name</th>
                            <th>Project Type</th>
                            <th>Assign To</th>
                            <th>Note</th>
                            <th>Expense</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="(transaction, sl) in project_visit.project_visit">
                            <td style="text-align:right">{{ sl + 1}}</td>
                            <td style="text-align:left;">{{ transaction.description }}</td>
                            <td>{{ transaction.expense_date }}</td>
                            <td>{{ transaction.project_name }}</td>
                            <td>{{ transaction.project_type }}</td>
                            <td>{{ transaction.assign_name }}</td>
                            <td>{{ transaction.note }}</td>
                            <td style="text-align:right">{{ transaction.amount }}</td>
                        </tr>
                    </tbody>

                     <tfoot>
                        <tr style="font-weight:bold;">
                            <td colspan="7" style="text-align:right;">Total &nbsp;</td>
                            <td style="text-align:right;">
                                {{ transactions.reduce((prev,curr) => prev + parseFloat(curr.amount), 0) }}
                            </td>
                        </tr>
                    </tfoot> -->

                <!-- </table>  -->

				<table 
					class="record-table">
					<thead>
						<tr>
							<th>Project Name</th>
                            <th>Work Name </th>
							<th>Address</th>
							<th> date </th>
							<th>Material Name</th>
							<th>Unit</th>
							<th>Total Estimated QTY</th>
							<th>Purpose of Revised Estimate</th>
							<th>Total Qty</th>
						</tr>
					</thead>
					<tbody>
						<template v-for="sale in project_visit.material_estimate">
							<tr>
								<td>{{ sale.name }}</td>
								<td>{{ sale.work_name }}</td>
								<td>{{ sale.address }}</td>
                                <td>{{ sale.date }}</td>
								<td style="text-align:right;">{{ sale.details[0].material_name }}</td>
								<td style="text-align:center;">{{ sale.details[0].unit }}</td>
								<td style="text-align:right;">{{ sale.details[0].total_estimated_qty }}</td>
								<td> {{sale.details[0].purpose_estimate }} </td>
                                <td style="text-align:right;"> {{parseFloat(sale.details[0].total_estimated_qty) + parseFloat(sale.details[0].purpose_estimate)}} </td>
								<!-- <td style="text-align:center;">
									<a href="" title="Sale Invoice" v-bind:href="`/sale_invoice_print/${sale.SaleMaster_SlNo}`" target="_blank"><i class="fa fa-file"></i></a>
									<a href="" title="Chalan" v-bind:href="`/chalan/${sale.SaleMaster_SlNo}`" target="_blank"><i class="fa fa-file-o"></i></a>
									<?php if ($this->session->userdata('accountType') != 'u') {?>
									<a href="javascript:" title="Edit Sale" @click="checkReturnAndEdit(sale)"><i class="fa fa-edit"></i></a>
									<a href="" title="Delete Sale" @click.prevent="deleteSale(sale.SaleMaster_SlNo)"><i class="fa fa-trash"></i></a>
									<?php }?>
								</td> -->
							</tr>
							 <tr v-for="(product, sl) in sale.details.slice(1)">
								<td colspan="4" v-bind:rowspan="sale.details.length - 1" v-if="sl == 0"></td>
								<td>{{ product.material_name }}</td>
								<td style="text-align:right;">{{ product.unit }}</td>
								<td style="text-align:center;">{{ product.total_estimated_qty }}</td>
								<td style="text-align:right;">{{ product.purpose_estimate }}</td>
                                <td style="text-align:right;"> {{ parseFloat(product.total_estimated_qty) + parseFloat(product.purpose_estimate) }} </td>
							</tr>
							<!-- <tr style="font-weight:bold;">
								<td colspan="7" style="font-weight:normal;"><strong>Note: </strong>{{ sale.SaleMaster_Description }}</td>
								<td style="text-align:center;">Total Quantity<br>{{ sale.saleDetails.reduce((prev, curr) => {return prev + parseFloat(curr.SaleDetails_TotalQuantity)}, 0) }}</td>
								<td style="text-align:right;">
									Total: {{ sale.SaleMaster_TotalSaleAmount }}<br>
									Paid: {{ sale.SaleMaster_PaidAmount }}<br>
									Due: {{ sale.SaleMaster_DueAmount }}
								</td>
								<td></td>
							</tr>  -->
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
			showTable:false,
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

            axios.post('/get_project_material_estimate', this.filter)
                .then(res => {
                    this.project_visit = res.data;
					console.log(this.project_visit.project_visit);
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