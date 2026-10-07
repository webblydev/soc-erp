
<div id="teams">
	<div class="row">
		<div class="col-md-4 col-md-offset-4 col-xs-12">
			<!-- Client entry-->
			<form method="post" @submit.prevent="saveTeam">
				<div class="row" style="padding-top: 5px;">
					<div class="form-group clearfix">
						<label class="control-label col-md-4">Team Name:</label>
						<div class="col-md-8">
							<input type="text" class="form-control" v-model="team.name" required>
						</div>
					</div>
					<div class="form-group clearfix">
						<label class="control-label col-md-4"></label>
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
					<datatable :columns="columns" :data="teams" :filter-by="filter" style="margin-bottom: 5px;">
						<template scope="{ row }">
							<tr>
								<td>{{ row.sl }}</td>
								<td>{{ row.name }}</td>
								<td>
									<button type="button" class="button edit" @click="editTeam(row)">
									<i class="fa fa-pencil"></i>
									</button>
									<button type="button" class="button" @click="deleteTeam(row.id)">
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
		el: '#teams',
		data(){
			return {
				team: {
					id: 0,
					name: '',
				},
				teams: [],
				columns: [
                    { label: 'Serial', field: 'sl', align: 'center', filterable: false },
                    { label: 'Team Name', field: 'name', align: 'center' },
                    { label: 'Action', align: 'center', filterable: false }
                ],
                page: 1,
                per_page: 10,
                filter: ''
			}
		},

		created(){
			this.getTeams();
		},

		methods: {
			getTeams(){
				axios.get("/get-teams").then(res => {
					this.teams = res.data.map((item, sl)=>{
						item.sl =  sl + 1;
						return item;
					});
				})
			},
			saveTeam(){
				let url = "";
				if(this.team.id != 0){
					url = "/update-team";
				} else {
					url = "/add-team"
					delete this.team.id
				}

				axios.post(url, this.team)
				.then(res=>{
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.resetForm();
						this.getTeams();
					}
				})
			},
			editTeam(team){
				let keys = Object.keys(this.team).forEach(key => {
					this.team[key] = team[key];
				})
			},
			deleteTeam(id) {
				let deleteConfirm = confirm('Are you sure?');
				if(deleteConfirm == false){
					return;
				}
				axios.post("/delete-team", {id: id})
				.then(res => {
					let r = res.data;
					alert(r.message);
					if(r.success){
						this.getTeams();
					}
				})
			},
			resetForm(){
				let keys = Object.keys(this.team);
				keys.forEach(key => {
					if(typeof(this.team[key]) == 'string'){
						this.team[key] = '';
					} else if(typeof(this.team[key]) == 'number'){
						this.team[key] = 0;
					}
				})
			}
		}
	})
</script>
