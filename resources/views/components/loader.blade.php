<div id="loader" class="hidden justify-center items-center"
    style="
        width: 100vw;
        height: 100vh;
        position: fixed;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 16px;
        box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        z-index: 99999;
        top: 0;
        left: 0;
      ">
    <img src="/loading.gif" alt="" style="width: 180px" />
    <h4 style="position: absolute; z-index: 1" class="">Loading...</h4>
</div>
<script>
    function startLoading() {
        $("#loader").removeClass("hidden");
        $("#loader").addClass("flex");
        // bloquear el scroll
        $("body").css("overflow", "hidden");
    }

    function stopLoading() {
        $("#loader").removeClass("flex");
        $("#loader").addClass("hidden");

        // desbloquear el scroll
        $("body").css("overflow", "auto");
    }
</script>
