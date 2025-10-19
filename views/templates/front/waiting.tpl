<div style="text-align:center; padding:50px; font-family:Arial, sans-serif; color:#212023;">
    <img src="{$module_dir}/logo_waiting.png" alt="Venti" style="width:100px; margin-bottom:30px;">
    <h2 style="color:#212023;">Estamos esperando la confirmación de tu pago...</h2>
    {if !empty($order_id)}
        <p>Tu pedido #{$order_id} se actualizará automáticamente.</p>
    {/if}
    <div style="
        border: 8px solid #FDFAFF;
        border-top: 8px solid #65DB95;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        margin: 30px auto;
        animation: spin 2s linear infinite;
    "></div>
    {if empty($order_id)}
        <p>Tu pedido aún no se ha generado.</p>
    {/if}
</div>

<style>
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>

<script>
setTimeout(function() {
    location.reload();
}, 5000);
</script>