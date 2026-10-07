<style>
v-select {
    float: right;
    min-width: 180px;
    margin-left: 5px;
    margin-bottom: 5px;
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

<div id="areas">
    <div class="row">
        <div class="col-md-6 col-md-offset-3 col-xs-12">
            <!-- Client entry-->
            <form method="post" @submit.prevent="saveProject">
                <div class="row" style="padding-top: 5px;">

                    <div class="form-group clearfix">
                        <label class="control-label col-md-3">Project Id:</label>
                        <div class="col-md-8">
                            <input type="text" class="form-control" v-model="project.project_id" required>
                        </div>
                    </div>

                    <div class="form-group clearfix">
                        <label class="control-label col-md-3">Project Name:</label>
                        <div class="col-md-8">
                            <input type="text" class="form-control" v-model="project.name" required>
                        </div>
                    </div>

                    <div class="form-group clearfix">
                        <label class="control-label col-md-3">Select Project Type:</label>
                        <div class="col-md-8">
                            <v-select v-bind:options="projectTypes" v-model="selectedProjectType" label="name">
                            </v-select>
                        </div>
                    </div>

                    <div class="form-group clearfix" style="margin-top:5px;">
                        <label class="control-label col-md-3"></label>
                        <div class="col-md-4">
                            <input type="submit" name="submit" class="btn btn-primary btn-block pull-right col-md-4"
                                value="Save">
                        </div>
                    </div>

                </div>
            </form>
            <!-- Client entry -->
        </div><!-- /.col -->
    </div><!-- /.row -->
    <hr style="margin-top: 10px;margin-bottom: 10px;">
    <div class="row">
        <div class="col-md-7 col-md-offset-2">
            <div class="col-md-12 form-inline">
                <div class="form-group">
                    <label for="filter" class="sr-only">Filter</label>
                    <input type="text" class="form-control" v-model="filter" placeholder="Filter">
                </div>
            </div>
            <div class="col-md-12">
                <div class="table-responsive">
                    <datatable :columns="columns" :data="projects" :filter-by="filter" style="margin-bottom: 5px;">
                        <template scope="{ row }">
                            <tr>
                                <td>{{ row.sl }}</td>
                                <td>{{ row.project_id }}</td>
                                <td>{{ row.name }}</td>
                                <td>{{ row.project_type_name }}</td>
                                <td>
                                    <button type="button" class="button edit" @click="editProject(row)">
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                    <button type="button" class="button" @click="deleteProject(row.id)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </datatable>
                    <datatable-pager v-model="page" type="abbreviated" :per-page="per_page"
                        style="margin-bottom: 50px;"></datatable-pager>
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
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#areas',
    data() {
        return {
            project: {
                id: 0,
                project_id: '',
                name: '',
                project_type_id: ''
            },
            projects: [],
            selectedProjectType: null,
            projectTypes: [],
            columns: [{
                    label: 'Serial',
                    field: 'sl',
                    align: 'center',
                    filterable: false
                },
                {
                    label: 'Project id',
                    field: 'project_id',
                    align: 'center'
                },
                {
                    label: 'Project Name',
                    field: 'name',
                    align: 'center'
                },
                {
                    label: 'Project Type',
                    field: 'project_type_name',
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
        this.projectType();
    },

    methods: {
        getProjects() {
            axios.get("/get-projects").then(res => {
                this.projects = res.data.map((ele, ind) => {
                    ele.sl = ind + parseFloat(1)
                    return ele;
                });
            })
        },
        saveProject() {

            let url = "/add-project";
            if (this.project.id != 0) {
                url = "/update-project";
            }

            this.project.project_type_id = this.selectedProjectType.id;

            axios.post(url, this.project).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.resetForm();
                    this.getProjects();
                }
            })
        },

        projectType() {
            axios.get("/get-types").then((res) => {
                this.projectTypes = res.data;
            });
        },

        editProject(area) {
            let keys = Object.keys(this.project);
            keys.forEach(key => {
                this.project[key] = area[key];
            });

            this.selectedProjectType = {
                id: area.project_type_id,
                name: area.project_type_name
            }

            console.log(area.project_type_id);


        },
        deleteProject(id) {
            let deleteConfirm = confirm('Are you sure?');
            if (deleteConfirm == false) {
                return;
            }
            axios.post("/delete-project", {
                areaId: id
            }).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.getProjects();
                }
            })
        },
        resetForm() {
            let keys = Object.keys(this.project);
            keys.forEach(key => {
                if (typeof(this.project[key]) == 'string') {
                    this.project[key] = '';
                } else if (typeof(this.project[key]) == 'number') {
                    this.project[key] = 0;
                }
            })

            this.selectedProjectType = null;
        }
    }




})
</script>