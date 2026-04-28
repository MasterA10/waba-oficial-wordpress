<?php
/**
 * Global Modal for Sending Templates with Variable Support and Preview
 */
?>
<div id="was-send-template-modal" class="was-modal" style="display:none; position:fixed; z-index:11000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); overflow-y:auto;">
    <div class="was-modal-content" style="background:#f0f2f5; margin:5% auto; padding:0; width:800px; border-radius:12px; overflow:hidden; display:flex; flex-direction:row; box-shadow:0 20px 50px rgba(0,0,0,0.3);">
        
        <!-- Coluna de Configuração -->
        <div style="flex:1; background:white; padding:30px; max-height:80vh; overflow-y:auto;">
            <h2 style="margin-top:0;">Enviar Modelo</h2>
            <form id="was-send-template-form">
                <input type="hidden" id="send-tpl-id">
                
                <p><strong>Modelo selecionado:</strong> <span id="send-tpl-name-display" style="color:#008069;"></span></p>
                
                <div id="was-tpl-to-container" style="margin-top:20px;">
                    <label><strong>1. Destinatário (WhatsApp ID)</strong></label><br>
                    <input type="text" id="send-tpl-to" style="width:100%; margin-top:5px;" required placeholder="5511999999999">
                </div>

                <div id="was-tpl-variables-container" style="margin-top:25px; border-top:1px solid #eee; padding-top:20px;">
                    <label><strong>2. Preencher Variáveis</strong></label>
                    <div id="was-tpl-variables-inputs" style="margin-top:10px;">
                        <!-- Injetado via JS -->
                    </div>
                </div>

                <div style="margin-top:30px; display:flex; gap:10px;">
                    <button type="submit" class="button button-primary" style="background:#25d366; border-color:#25d366; flex:1; height:40px; font-weight:bold;">Enviar Agora</button>
                    <button type="button" id="was-close-send-modal" class="button" style="flex:1; height:40px;">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Coluna de Preview Realista -->
        <div style="width:350px; background:#e5ddd5; padding:40px 20px; display:flex; align-items:center; justify-content:center;">
            <div style="width:100%;">
                <p style="text-align:center; color:#667781; font-size:0.8rem; margin-bottom:15px; text-transform:uppercase; letter-spacing:1px;">Preview do Envio</p>
                <div class="was-wa-preview-card" style="width:100%; margin:0 auto;">
                    <div class="was-wa-header" id="send-pre-header" style="display:none;"></div>
                    <div class="was-wa-body" id="send-pre-body">...</div>
                    <div class="was-wa-footer" id="send-pre-footer" style="display:none;"></div>
                    <div class="was-wa-buttons" id="send-pre-buttons" style="display:none;"></div>
                </div>
                <p style="margin-top:20px; font-size:0.75rem; color:#667781; text-align:center;">As variáveis aparecerão aqui conforme você as preencher.</p>
            </div>
        </div>

    </div>
</div>
