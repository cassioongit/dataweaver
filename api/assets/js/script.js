$(function(){

    var fileInput = $('#fileUpload');
    var dropZone = $('#dropZone');
    var progressBar = $('#progressBar');
    var progressPercent = $('#progressPercent');
    var totalReadCount = $('#totalReadCount');
    var divergencesCount = $('#divergencesCount');
    var badgeStatus = $('#badgeStatus');
    var downloadButton = $('#downloadButton');
    var resultsSection = $('#resultsSection');
    var patientsList = $('#patientsList');
    var processingStatus = $('#processingStatus');
    var statusIndicator = $('#statusIndicator');

    $('#chooseFileButton').on('click', function(){
        fileInput.click();
    });

    // Initialize the jQuery File Upload plugin
    $('#uploadForm').fileupload({
        url: 'src/upload.php',
        dropZone: dropZone,
        dataType: 'json',

        add: function (e, data) {
            // Reset UI for new upload
            progressBar.css('width', '0%');
            progressPercent.text('0%');
            totalReadCount.text('---');
            divergencesCount.text('---');
            downloadButton.addClass('hidden');
            resultsSection.addClass('hidden');
            patientsList.empty();
            
            badgeStatus.text('PROCESSANDO')
                .removeClass('bg-surface-container text-on-surface-variant')
                .addClass('bg-secondary-container text-on-secondary-container');
            
            processingStatus.text('Enviando arquivo...');
            statusIndicator.removeClass('bg-slate-300').addClass('bg-primary animate-pulse');

            data.submit();
        },

        progress: function(e, data){
            var progress = parseInt(data.loaded / data.total * 100, 10);
            progressBar.css('width', progress + '%');
            progressPercent.text(progress + '%');
            
            if(progress == 100){
                processingStatus.text('Reconciliando base de dados...');
            }
        },

        done: function(e, data){
            var r = data.result;
            
            if(r.status == 'success'){
                // Update basic stats
                totalReadCount.text(r.total_read);
                divergencesCount.text(r.divergences);
                
                // Show download button
                downloadButton.attr('href', r.file).removeClass('hidden');
                
                // Populate results table
                if(r.added && r.added.length > 0){
                    resultsSection.removeClass('hidden');
                    $.each(r.added, function(index, patient){
                        var row = $(`
                            <div class="grid grid-cols-12 items-center px-8 py-5 hover:bg-surface-container-low/30 transition-colors">
                                <div class="col-span-7 flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-primary-fixed/30 flex items-center justify-center text-primary font-bold text-xs">
                                        ${patient.nome.substring(0, 2).toUpperCase()}
                                    </div>
                                    <span class="font-semibold text-on-surface">${patient.nome}</span>
                                </div>
                                <div class="col-span-3 font-mono text-xs text-on-surface-variant">#${patient.id}</div>
                                <div class="col-span-2 text-sm text-right text-on-surface-variant">${patient.data}</div>
                            </div>
                        `);
                        patientsList.append(row);
                    });
                }
                
                badgeStatus.text('CONCLUÍDO')
                    .removeClass('bg-secondary-container text-on-secondary-container')
                    .addClass('bg-primary-container text-on-primary-container');
                
                processingStatus.text('Processamento concluído com sucesso.');
                statusIndicator.removeClass('animate-pulse').addClass('bg-primary');

                if(r.mensagem != '' && r.divergences == 0){
                    // alert('Aviso: Nenhum novo registro encontrado.');
                }
            } else {
                handleError(r.erro || 'Erro desconhecido durante o processamento.');
            }
        },

        fail: function(e, data){
            handleError('Falha na comunicação com o servidor.');
        }
    });

    function handleError(msg) {
        badgeStatus.text('ERRO')
            .removeClass('bg-secondary-container text-on-secondary-container')
            .addClass('bg-error-container text-on-error-container');
        
        processingStatus.text(msg);
        statusIndicator.removeClass('animate-pulse').addClass('bg-error');
        alert('Erro: ' + msg);
    }

    // Prevent default drag/drop behavior outside dropZone
    $(document).on('drop dragover', function (e) {
        e.preventDefault();
    });

    // Dropzone visual feedback
    dropZone.on('dragover', function(){ dropZone.addClass('border-primary'); });
    dropZone.on('dragleave drop', function(){ dropZone.removeClass('border-primary'); });

});
