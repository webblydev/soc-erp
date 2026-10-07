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

	#project label {
		font-size: 13px;
	}

	#project select {
		border-radius: 3px;
	}

	#project .add-button {
		padding: 2.5px;
		width: 28px;
		background-color: #298db4;
		display: block;
		text-align: center;
		color: white;
	}

	#project .add-button:hover {
		background-color: #41add6;
		color: white;
	}

	.addButton {
		background-color: green;
		border: 1px solid #ccc;
		border-radius: 7px;
		color: #fff;
		padding: 5px 10px;
		margin-top: 20px;
	}

	.updateButton {
		background-color: #d35400;
		border: 1px solid #ccc;
		border-radius: 7px;
		color: #fff;
		padding: 5px 10px;
		margin-top: 20px;
	}

	.DeleteButton {
		background-color: red;
		border: 1px solid #ccc;
		border-radius: 7px;
		color: #fff;
		padding: 5px 10px;
		margin-top: 20px;
	}
</style>
<div id="project">
	<div class="row" style="margin-top: 10px;margin-bottom:15px;border-bottom: 1px solid #ccc;padding-bottom: 15px;">
		<div class="col-md-6">
			<div class="form-group clearfix">
				<label class="control-label col-md-4">Name of Permitee:</label>
				<div class="col-md-7">
					<input type="text" class="form-control" v-model="project.permitee_name">
				</div>
			</div>

			<div class="form-group clearfix">
				<label class="control-label col-md-4">Project:</label>
				<div class="col-md-7">
					<select class="form-control" v-if="projects.length == 0"></select>
					<v-select v-bind:options="projects" v-model="selectedProject" label="name"></v-select>
				</div>
				<div class="col-md-1" style="padding:0;margin-left: -15px;"><a href="/project" target="_blank" class="add-button"><i class="fa fa-plus"></i></a></div>
			</div>


			<div class="form-group clearfix">
				<label class="control-label col-md-4">Location:</label>
				<div class="col-md-7">
					<input type="text" class="form-control" v-model="project.location" required>
				</div>
			</div>

			<div class="form-group clearfix">
				<label class="control-label col-md-4">Construction Side ID:</label>
				<div class="col-md-7">
					<input type="text" class="form-control" v-model="selectedProject.project_id" required>
				</div>
			</div>

			<div class="form-group clearfix">
				<label class="control-label col-md-4">Contractor Name:</label>
				<div class="col-md-7">
					<input type="text" class="form-control" v-model="project.constructor_name">
				</div>
			</div>


			<div class="form-group clearfix">
				<label class="control-label col-md-4">Project Eng. Name:</label>
				<div class="col-md-7">
					<input type="text" class="form-control" v-model="project.project_eng_name">
				</div>
			</div>

			<div class="form-group clearfix">
				<label class="control-label col-md-4">Field Office Phone:</label>
				<div class="col-md-7">
					<input type="text" class="form-control" v-model="project.field_office_phone">
				</div>
			</div>

		</div>
		<div class="col-md-6">
			<div class="form-group clearfix">
				<label class="control-label col-md-4">Date of Inspection</label>
				<div class="col-md-7">
					<input type="date" class="form-control" v-model="project.inspection_date" required>
				</div>
			</div>

			<div class="form-group clearfix">
				<label class="control-label col-md-4">Time of Inspection:</label>
				<div class="col-md-8">
					<label>Start Time: <input type="time" class="form-control" v-model="project.start_time" required></label>
					<label>End Time: <input type="time" class="form-control" v-model="project.end_time" required></label>
				</div>
			</div>


			<div class="form-group clearfix">
				<label class="control-label col-md-4">Type of Inspection:</label>
				<div class="col-md-7">
					<label><input type="checkbox" value="weekly" v-model="project.inspection_type_weekly" :checked="project.inspection_type_weekly == 1 ? true : false"> Weekly </label>
					<label><input type="checkbox" value="pre_event" v-model="project.inspection_type_event" :checked="project.inspection_type_event == 1 ? true : false">Precipitation Event</label>
					<!-- Add more checkboxes here if needed -->
				</div>
			</div>

			<!-- <div class="form-group clearfix">
						<label class="control-label col-md-4">Description:</label>
						<div class="col-md-7">
							<textarea  name="" id="" cols="30" rows="10"></textarea>
						</div>
					</div> -->

			<div class="form-group clearfix">
				<label class="control-label col-md-4">Description</label>
				<div class="col-md-7">
					<textarea v-model="project.description" id="" cols="42" rows="10"></textarea>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<form v-on:submit.prevent="addToVisit">
			<div class="col-md-3">
				<label for="">Location of Finding</label>
				<div>
					<input type="text" class="form-control" v-model="visitDetailsCart.location">
				</div>
			</div>

			<div class="col-md-3">
				<label for="">Description</label>
				<div>
					<input type="text" class="form-control" v-model="visitDetailsCart.visit_description">
				</div>
			</div>

			<div class="col-md-2">
				<label for="">Finding By</label>
				<div>
					<input type="text" class="form-control" v-model="visitDetailsCart.finding">
				</div>
			</div>

			<div class="col-md-3">
				<label for="">Regarding Taking</label>
				<div>
					<input type="radio" id="yes" name="yes" value="yes" v-model="visitDetailsCart.regarding" />
					<label for="yes">Yes</label>

					<input type="radio" id="no" name="no" value="no" v-model="visitDetailsCart.regarding" />
					<label for="no">No</label>

					<input type="radio" id="no_application" name="no_application" value="no_application" v-model="visitDetailsCart.regarding" />
					<label for="no_application">Not Application</label><br />
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
		<div class="row" v-for="(visit, sl) in visitCart" style="margin-bottom: 15px;">

			<div class="col-md-3">
				<label for="">Location of Finding</label>
				<div>
					<input type="text" class="form-control" v-model="visit.visit_location">
				</div>
			</div>

			<div class="col-md-3">
				<label for="">Description</label>
				<div>
					<input type="text" class="form-control" v-model="visit.visit_description">
				</div>
			</div>

			<div class="col-md-2">
				<label for="">Finding By</label>
				<div>
					<input type="text" class="form-control" v-model="visit.visit_finding">
				</div>
			</div>

			<div class="col-md-3">
				<label for="">Regarding Taking</label>
				<div>
					<input type="radio" id="yes" name="yes" value="yes" v-model="visit.visit_regarding" />
					<label for="yes">Yes</label>

					<input type="radio" id="no" name="no" value="no" v-model="visit.visit_regarding" />
					<label for="no">No</label>

					<input type="radio" id="no_application" name="no_application" value="no_application" v-model="visit.visit_regarding" />
					<label for="no_application">Not Application</label><br />
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
					<input type="submit" :class="project.Project_Visit_SlNo == 0 ? 'addButton' : 'updateButton'" :value="project.Project_Visit_SlNo != 0 ? 'Update' : 'Save' " v-on:click="saveProjectVisit">
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
				<datatable :columns="columns" :data="all_project_visit.project_visit" :filter-by="filter">
					<template scope="{ row }">
						<tr>
							<td>{{ row.construction_id }}</td>
							<td>{{ row.constructor_name }}</td>
							<td>{{ row.name }}</td>
							<td>{{ row.project_eng_name }} </td>
							<td>{{ row.permitee_name }}</td>
							<td>{{ row.field_office_phone }}</td>
							<td>{{ row.location }}</td>
							<td>{{ row.start_time }}</td>
							<td>{{ row.end_time }}</td>
							<td>{{ row.inspection_date }}</td>
							<td>{{ row.description }}</td>
							<td>
								<?php if ($this->session->userdata('accountType') != 'u') { ?>
									<button type="button" class="button edit" @click="editProject(row)">
										<i class="fa fa-pencil"></i>
									</button>
									<button type="button" class="button" @click="deleteProduct(row.Project_Visit_SlNo)">
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

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
	Vue.component('v-select', VueSelect.VueSelect);
	new Vue({
		el: '#project',
		data() {
			return {
				project: {
					Project_Visit_SlNo: '',
					construction_id: "",
					permitee_name: '',
					project_id: '',
					location: '',
					constructor_name: '',
					project_eng_name: '',
					field_office_phone: '',
					inspection_date: '',
					start_time: '',
					end_time: '',
					inspection_type_weekly: '',
					inspection_type_event: '',
					description: '',
				},
				projects: [],
				visitDetailsCart: {
					visit_location: '',
					visit_description: '',
					visit_finding: '',
					visit_regarding: ''
				},
				visitCart: [],
				selectedProject: {
					project_id: '',
				},
				all_project_visit: [],

				columns: [{
						label: 'Construction Id',
						field: 'construction_id',
						align: 'center',
						filterable: false
					},
					{
						label: 'Constructor Name',
						field: 'constructor_name',
						align: 'center'
					},
					{
						label: 'Project Name',
						field: 'name',
						align: 'center'
					},
					{
						label: 'Project Eng Name',
						field: 'project_eng_name',
						align: 'center'
					},
					{
						label: 'Permitee Name',
						field: 'permitee_name',
						align: 'center'
					},
					{
						label: 'Field Officer Phone',
						field: 'field_office_phone',
						align: 'center'
					},
					{
						label: 'Location',
						field: 'location',
						align: 'center'
					},
					{
						label: 'Start Time',
						field: 'start_time',
						align: 'center'
					},
					{
						label: 'End Time',
						field: 'end_time',
						align: 'center'
					},
					{
						label: 'Inspection Date',
						field: 'inspection_date',
						align: 'center'
					},
					{
						label: 'Description',
						field: 'description',
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
			this.getProjects();
			this.getProjectVisit();
		},
		methods: {
			changeIsService() {
				if (this.product.is_service) {
					this.product.Product_Purchase_Rate = 0;
				}
			},
			getProjects() {
				axios.get('/get-projects').then(res => {
					this.projects = res.data;
				})
			},



			getProjectVisit() {
				axios.get('/get_project_visit').then(res => {
					this.all_project_visit = res.data;
				})
			},

			addToVisit() {
				let visit = {
					visit_location: this.visitDetailsCart.location,
					visit_description: this.visitDetailsCart.visit_description,
					visit_finding: this.visitDetailsCart.finding,
					visit_regarding: this.visitDetailsCart.regarding,
				}
				this.visitCart.unshift(visit);
				this.clearVisitCart();
			},

			removeItem(ind) {
				this.visitCart.splice(ind, 1);
			},
			saveProjectVisit() {


				if (this.selectedProject == null) {
					alert("Please Select Project");
					return;
				}

				if (this.visitCart.length == 0) {
					alert("Your Visiting cart Is Empty");
					return;
				}

				this.project.project_id = this.selectedProject.id;
				this.project.construction_id = this.selectedProject.project_id;
				// this.product.Unit_ID = this.selectedUnit.Unit_SlNo;

				let url = '/add_project_visit';
				if (this.project.Project_Visit_SlNo != 0) {
					url = '/update_project_visit';
				}

				let data = {
					project_visit: this.project,
					cart: this.visitCart
				}

				axios.post(url, data)
					.then(res => {
						let r = res.data;
						alert(r.message);
						if (r.success) {
							this.clearForm();
							// this.product.Product_Code = r.productId;
							this.getProjectVisit();
						}
					})

			},
			editProject(product) {
				let keys = Object.keys(this.project);
				keys.forEach((key) => {
					this.project[key] = product[key];
				})
				console.log(keys);
				this.selectedProject = {
					id: product.project_id,
					project_id: product.construction_id,
					name: product.name
				}
				product.details.forEach((item) => {
					let visit = {
						visit_location: item.visit_location,
						visit_description: item.visit_description,
						visit_finding: item.visit_finding,
						visit_regarding: item.visit_regarding,
					}
					this.visitCart.unshift(visit);
				})
			},
			deleteProduct(visitId) {
				let deleteConfirm = confirm('Are you sure?');
				if (!deleteConfirm) {
					return;
				}
				axios.post('/delete_project_visit', {
					visitId: visitId
				}).then(res => {
					let r = res.data;
					alert(r.message);
					if (r.success) {
						this.getProjectVisit();
					}
				});
			},
			clearForm() {
				let keys = Object.keys(this.project);
				keys.forEach(key => {
					if (typeof(this.project[key]) == "string") {
						this.project[key] = '';
					} else if (typeof(this.project[key]) == "number") {
						this.product[key] = 0;
					}
				})
				this.selectedProject.id = null;
				this.selectedProject.name = '';
				this.selectedProject.project_id = '';

				this.visitCart = [];

			},
			clearVisitCart() {
				this.visitDetailsCart = {
					visit_location: '',
					visit_description: '',
					visit_finding: '',
					visit_regarding: ''
				}
			},
		}
	})
</script>