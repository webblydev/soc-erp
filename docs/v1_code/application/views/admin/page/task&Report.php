<div id="taskInvoice">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <sales-invoice v-bind:task_id="taskId"></sales-invoice>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/components/taskInvoice.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>
<script>
new Vue({
    el: '#taskInvoice',
    components: {
        taskInvoice
    },
    data() {
        return {
            taskId: parseInt('<?php echo $taskId; ?>')
        }
    }
})
</script>