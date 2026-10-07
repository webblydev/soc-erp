<div id="visitInvoice">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <sales-invoice v-bind:visit_id="visitId"></sales-invoice>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/components/vendorBillInvoice.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>
<script>
new Vue({
    el: '#visitInvoice',
    components: {
        visitInvoice
    },
    data() {
        return {
            visitId: parseInt('<?php echo $visitId; ?>')
        }
    }
})
</script>
