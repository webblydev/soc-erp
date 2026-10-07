
<div id="areas">
		
	<div class="row">
		<div class="col-md-4 col-md-offset-4 col-xs-12">
			<!-- Client entry-->
			<form method="post" @submit.prevent="saveArea">
				<div class="row" style="padding-top: 5px;">
					<div class="form-group clearfix">
						<label class="control-label col-md-3">Aare Name:</label>
						<div class="col-md-8">
							<input type="text" class="form-control" v-model="area.name" required>
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
					<datatable :columns="columns" :data="areas" :filter-by="filter" style="margin-bottom: 5px;">
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
		el: '#areas',
		data(){
			return {
				area: {
					id: 0,
					name: '',
				},
				areas: [],
				columns: [
                    { label: 'Serial', field: 'sl', align: 'center', filterable: false },
                    { label: 'Area Name', field: 'name', align: 'center' },
                    { label: 'Action', align: 'center', filterable: false }
                ],
                page: 1,
                per_page: 10,
                filter: ''
			}
		},

		created(){
			this.getAreas();
		},

		methods: {
			getAreas(){
				axios.get("/get-areas").then(res => {
					this.areas = res.data.map((ele,ind)=>{
							ele.sl = ind+parseFloat(1)
							return ele;
					});
				})
			},
			saveArea(){
				let url = "/add-area";
				if(this.area.id != 0){
					url = "/update-area";
				}

				axios.post(url, this.area).then(res=>{
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.resetForm();
						this.getAreas();
					}
				})
			},
			editArea(area){
				let keys = Object.keys(this.area);
				keys.forEach(key => {
					this.area[key] = area[key];
				})
			},
			deleteArea(id) {
				let deleteConfirm = confirm('Are you sure?');
				if(deleteConfirm == false){
					return;
				}
				axios.post("/delete-area", {areaId: id}).then(res => {
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.getAreas();
					}
				})
			},
			resetForm(){
				let keys = Object.keys(this.area);
				keys.forEach(key => {
					if(typeof(this.area[key]) == 'string'){
						this.area[key] = '';
					} else if(typeof(this.area[key]) == 'number'){
						this.area[key] = 0;
					}
				})
			}
		}
	})
</script>
