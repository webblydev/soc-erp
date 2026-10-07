
<div id="works">
		
	<div class="row">
		<div class="col-md-5 col-md-offset-3 col-xs-12">
			<!-- Client entry-->
			<form method="post" @submit.prevent="saveArea">
				<div class="row" style="padding-top: 5px;">
					<div class="form-group clearfix">
						<label class="control-label col-md-3">Work Item Name:</label>
						<div class="col-md-8">
							<input type="text" class="form-control" v-model="work.name" required>
						</div>
					</div>
					<div class="form-group clearfix">
						<label class="control-label col-md-3"></label>
						<div class="col-md-4">
							<input type="submit" name="submit" class="btn btn-primary btn-block pull-right col-md-4" value="Save">
						</div>
					</div>
				</div>
			</form>
			<!-- Client entry -->
		</div><!-- /.col -->
	</div><!-- /.row -->
	<hr style="margin-top: 10px;margin-bottom: 10px;">
	<div class="row">
		<div class="col-md-6 col-md-offset-3">		
			<div class="col-md-12 form-inline">
				<div class="form-group">
					<label for="filter" class="sr-only">Filter</label>
					<input type="text" class="form-control" v-model="filter" placeholder="Filter">
				</div>
			</div>
			<div class="col-md-12">
				<div class="table-responsive">
					<datatable :columns="columns" :data="works" :filter-by="filter" style="margin-bottom: 5px;">
						<template scope="{ row }">
							<tr>
								<td>{{ row.sl }}</td>
								<td>{{ row.name }}</td>
								<td>
									<button type="button" class="button edit" @click="editArea(row)">
									<i class="fa fa-pencil"></i>
									</button>
									<button type="button" class="button" @click="deleteArea(row.id)">
										<i class="fa fa-trash"></i>
									</button>
								</td>
							</tr>
						</template>
					</datatable>
					<datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;"></datatable-pager>
				</div>			
				
			</div>
		</div>
	</div>
</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
	new Vue({
		el: '#works',
		data(){
			return {
				work: {
					id: 0,
					name: '',
				},
				works: [],
				columns: [
                    { label: 'Serial', field: 'sl', align: 'center', filterable: false },
                    { label: 'Work Item Name', field: 'name', align: 'center' },
                    { label: 'Action', align: 'center', filterable: false }
                ],
                page: 1,
                per_page: 10,
                filter: ''
			}
		},

		created(){
			this.getWorkItems();
		},

		methods: {
			getWorkItems(){
				axios.get("/get-workItems").then(res => {
					this.works = res.data.map((ele,ind)=>{
							ele.sl = ind+parseFloat(1)
							return ele;
					});
				})
			},
			saveArea(){
				let url = "/add-workItem";
				if(this.work.id != 0){
					url = "/update-workItem";
				}

				axios.post(url, this.work).then(res=>{
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.resetForm();
						this.getWorkItems();
					}
				})
			},
			editArea(workItem){
				let keys = Object.keys(this.work);
				keys.forEach(key => {
					this.work[key] = workItem[key];
				})
			},
			deleteArea(id) {
				let deleteConfirm = confirm('Are you sure?');
				if(deleteConfirm == false){
					return;
				}
				axios.post("/delete-workItem", {workItemId: id}).then(res => {
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.getWorkItems();
					}
				})
			},
			resetForm(){
				let keys = Object.keys(this.work);
				keys.forEach(key => {
					if(typeof(this.work[key]) == 'string'){
						this.work[key] = '';
					} else if(typeof(this.work[key]) == 'number'){
						this.work[key] = 0;
					}
				})
			}
		}
	})
</script>
