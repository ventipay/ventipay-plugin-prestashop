<div style="text-align:center; padding:50px;">

    {if !empty($order_id)}
        <p>DEBUG: Order ID = {$order_id}</p>
        <p>Tu pedido (#{$order_id}) se actualizará automáticamente.</p>
    {/if}

    <!-- Loader animado -->
    <img src="https://i.gifer.com/ZZ5H.gif" alt="Cargando..." style="width:100px;height:100px; margin-top:20px;">

    <h2>Estamos esperando la confirmación de tu pago...</h2>

    {if empty($order_id)}
        <p>Tu pedido aún no se ha generado.</p>
    {/if}

</div>

<script>
// Recarga automática cada 5 segundos para volver a verificar el estado
setTimeout(function() {
    location.reload();
}, 5000);
</script>