
<div id="areas">
	<div class="row">
		<div class="col-md-4 col-md-offset-4 col-xs-12">
			<!-- Client entry-->
			<form method="post" @submit.prevent="saveArea">
				<div class="row" style="padding:15px 0px">
						<div class="form-group clearfix">
							<label class="control-label col-md-3">Service:</label>
							<div class="col-md-9">
								<input type="text" class="form-control" v-model="software.soft_name" required>
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
					<datatable :columns="columns" :data="softwares" :filter-by="filter" style="margin-bottom: 5px;">
						<template scope="{ row }">
							<tr>
								<td>{{ row.sl }}</td>
								<td>{{ row.soft_name }}</td>
								<td>
									<button type="button" class="button edit" @click="editSoftware(row)">
									<i class="fa fa-pencil"></i>
									</button>
									<button type="button" class="button" @click="deleteSoftware(row.id)">
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

<script>
	new Vue({
		el: '#areas',
		data(){
			return {
				software: {
					id: 0,
					soft_name: '',
				},
				softwares: [],
				columns: [
                    { label: 'Serial', field: 'sl', align: 'center', filterable: false },
                    { label: ' Service Name', field: 'name', align: 'center' },
                    { label: 'Action', align: 'center', filterable: false }
                ],
                page: 1,
                per_page: 10,
                filter: ''
			}
		},

		created(){
			this.getSoftware();
		},

		methods: {
			getSoftware(){
				axios.get('/get-software').then(res => {
					this.softwares = res.data.map((ele,ind)=>{
							ele.sl = ind+parseFloat(1)
							return ele;
					});
				})
			},
			saveArea(){
				let url = 'add-software';
				if(this.software.id != 0){
					url = '/update-software';
				}

				axios.post(url, this.software).then(res=>{
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.resetForm();
						this.getSoftware();
						
					}
				})
			},
			editSoftware(software){
				let keys = Object.keys(this.software);
				keys.forEach(key => {
					this.software[key] = software[key];
				})
			},
			deleteSoftware(id) {
				let deleteConfirm = confirm('Are you sure?');
				if(deleteConfirm == false){
					return;
				}
				axios.post('/delete-software', {softId: id}).then(res => {
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.getSoftware();
					}
				})
			},
			resetForm(){
				let keys = Object.keys(this.software);
				keys.forEach(key => {
					if(typeof(this.software[key]) == 'string'){
						this.software[key] = '';
					} else if(typeof(this.software[key]) == 'number'){
						this.software[key] = 0;
					}
				})
			}
		}
	})
</script>
