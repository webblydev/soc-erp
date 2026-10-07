const taskComponent = Vue.component("modal", {
	template: `
        <transition name="modal">
            <div class="modal-mask">
                <div class="modal-wrapper">
                    <div class="modal-container">
                        <div class="modal-header">
                            <slot name="header">
                                <h3>{{ task.project_name }}</h3>
                                <p style="margin:0px;">
                                    {{ task.client_name }}<br>
                                    {{ task.client_phone }}
                                </p>
                            </slot>
                        </div>

                        <div class="modal-body">
                            <slot name="body">
                                <div>{{ task.task_detail }}</div>
                                <div style="margin-top: 25px;" v-if="viewas == 'user'">
                                    <label>Comment:</label><br>
                                    <textarea v-model="task.completed_by_comment" style="width:100%;min-height:70px;"></textarea>
                                </div>
                                <div style="margin-top: 25px;" v-else>
                                    <strong>{{ task.assigned_person }}'s Comment:</strong>
                                    {{ task.completed_by_comment }}
                                </div>
                            </slot>
                        </div>

                        <div class="modal-footer">
                            <slot name="footer">
                                <div class="row">
                                    <div class="col-md-6 text-left">
                                        <label>Status:</label>
                                        <select v-model="task.status">
                                            <option value="p">Pending</option>
                                            <option value="o">On Progress</option>
                                            <option value="c">Completed</option>
                                            <option value="a" v-if="viewas == 'admin'">Archived</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <button class="btn btn-success btn-sm" @click="updateTask">Done</button>
                                        <button class="btn btn-danger btn-sm" @click="$emit('close')">Cancel</button>
                                    </div>
                                </div>
                            </slot>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    `,
	props: ["task", "viewas"],
	data() {
		return {
			style: null,
		};
	},
	created() {
		this.setStyle();
	},
	methods: {
		setStyle() {
			this.style = document.createElement("style");
			this.style.innerHTML = `
                .modal-mask {
                    position: fixed;
                    z-index: 9998;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, .5);
                    display: table;
                    transition: opacity .3s ease;
                }
                
                .modal-wrapper {
                    display: table-cell;
                    vertical-align: middle;
                }
                
                .modal-container {
                    width: 600px;
                    margin: 0px auto;
                    padding: 0;
                    background-color: #fff;
                    border-radius: 2px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, .33);
                    transition: all .3s ease;
                    font-family: Helvetica, Arial, sans-serif;
                }
                
                .modal-header h3 {
                    margin-top: 0;
                    color: #42b983;
                }
                
                .modal-body {
                    white-space: pre-line;
                }
                
                .modal-default-button {
                    float: right;
                }
                
                .modal-enter {
                    opacity: 0;
                }
                
                .modal-leave-active {
                    opacity: 0;
                }
                
                .modal-enter .modal-container,
                .modal-leave-active .modal-container {
                    -webkit-transform: scale(1.1);
                    transform: scale(1.1);
                }
            `;
			document.head.appendChild(this.style);
		},
		updateTask() {
			let a =
				this.task.status == "c"
					? moment().format("YYYY-MM-DD")
					: this.task.completed_date;
			this.task.completed_date =
				this.task.status == "c"
					? moment().format("YYYY-MM-DD")
					: this.task.completed_date;
			console.log(moment().format("YYYY-MM-DD"));
			axios
				.post("/change-task-status", this.task)
				.then((res) => {
					let r = res.data;
					if (r.success) {
						this.$emit("close");
						window.open("/task_invoice_print/" + r.taskId, "_blank");
						new Promise((r) => setTimeout(r, 1000));
					} else {
						alert(r.message);
					}
				})
				.catch((error) => {
					if (error.response) {
						alert(error.response.statusText);
					}
				});
		},
	},
});
