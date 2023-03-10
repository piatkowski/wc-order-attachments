(function ($) {
    "use strict";
    var fileQueue = [];
    $(document).ready(function () {
        console.log('Ready');
    });
    $('#wcoa_files').on('change', function () {
        if ('files' in $(this)[0]) {
            for (var i = 0; i < $(this)[0].files.length; i++) {
                var queueObj = {
                    file: $(this)[0].files[i], preview: false, error: '', delete: false
                }
                const fileName = 'name' in queueObj.file ? queueObj.file.name : queueObj.file.fileName;
                if ('type' in queueObj.file && queueObj.file.type.startsWith('image/')) {
                    queueObj.preview = $('<img />');
                    queueObj.preview.attr('alt', fileName);
                    queueObj.preview.attr('src', URL.createObjectURL(queueObj.file));
                    queueObj.preview.load(function () {
                        URL.revokeObjectURL($(this).attr('src'));
                    })
                }
                fileQueue.push(queueObj);
            }
        }
        renderFileList();
    });

    function renderFileList() {
        showMessage('');
        $('#wcoa_upload_list').empty();
        if (fileQueue.length > 0) {
            for (var index = 0; index < fileQueue.length; index++) {
                const queueObject = fileQueue[index];
                const fileName = 'name' in queueObject.file ? queueObject.file.name : queueObject.file.fileName;
                const listItem = $('<li></li>');
                if (queueObject.preview) {
                    queueObject.preview.appendTo(listItem);
                }
                const fileNameElement = $('<span></span>');
                fileNameElement.html(fileName + ' (' + formatFileSize(queueObject.file.size) + ') ' + queueObject.error);
                fileNameElement.appendTo(listItem);

                const deleteItemButton = $('<a></a>');
                deleteItemButton.addClass(['wcoa_delete_item'])
                deleteItemButton.attr('data-item-index', index);
                deleteItemButton.text('X');
                deleteItemButton.appendTo(listItem);

                $('#wcoa_upload_list').append(listItem);
            }
        }
        const isListEmpty = $('#wcoa_upload_list li').length === 0;
        $('.wcoa_hide_on_empty').toggle(!isListEmpty);
    }

    $('#wcoa_clear_metadata').on('click', function (e) {
        $.ajax({
            url: ajaxurl, type: 'post', dataType: 'json', data: {
                action: 'wcoa_clear_metadata', order_id: $('#post_ID').val()
            }, success: function (response) {
                alert('Response: ' + response.data);
            }, error: function () {
                alert('Error!');
            }
        });
    });

    function updateUploadedFileList(html_content) {
        $('#wcoa_uploaded_files').html(html_content);
    }

    $('#wcoa_do_upload').on('click', function (e) {
        e.preventDefault();
        if (fileQueue.length === 0) return;

        var formData = new FormData();
        formData.append('_wcoa_nonce', $('input[name=_wcoa_nonce]').val());
        formData.append('action', 'wcoa_upload');
        formData.append('order_id', $('#post_ID').val());

        for (var i = 0; i < fileQueue.length; i++) {
            formData.append('file_' + i, fileQueue[i].file);
        }
        $.ajax({
            url: ajaxurl,
            type: 'post',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function (response) {
                if (!response.success) {
                    showMessage('Server Error: ' + response.data);
                } else {
                    response.data.uploads.forEach(function (status, fileIndex) {
                        if (status === 'OK') {
                            fileQueue[fileIndex].delete = true;
                        } else {
                            fileQueue[fileIndex].error = '<span class="wcoa_error">' + status + '</span>';
                        }
                    });
                    fileQueue = fileQueue.filter(function (item) {
                        return item.delete !== true;
                    });
                    renderFileList();
                    updateUploadedFileList(response.data.file_list);
                }
                updateProgressbar(0, false);
            },
            error: function (request, status, error) {
                showMessage('Network Error: ' + error);
                updateProgressbar(0, false);
            },
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                $('#wcoa_progress').show();
                xhr.upload.addEventListener("progress", function (e) {
                    if (e.lengthComputable) {
                        var progress = parseInt(e.loaded / e.total * 100);
                        updateProgressbar(progress);
                    }
                }, false);
                return xhr;
            }
        })
    });

    $('body').on('click', '.wcoa_remove_file', function (e) {
        e.preventDefault();
        if (!confirm('Are you sure? File will be removed from file system.')) {
            return;
        }
        const file_hash = $(this).data('fileHash');
        $("#wcoa_" + file_hash).css({opacity: 0.5});
        $.ajax({
            url: ajaxurl, type: 'post', dataType: 'json', data: {
                _wcoa_nonce: $('input[name=_wcoa_nonce]').val(),
                action: 'wcoa_remove_file',
                order_id: $('#post_ID').val(),
                file_hash: file_hash
            }, success: function (response) {
                if (response.success) {
                    $("#wcoa_" + file_hash).remove();
                } else {
                    alert(response.data);
                    $("#wcoa_" + file_hash).css({opacity: 1});
                }
            }, error: function () {
                alert('Request network error');
                $("#wcoa_" + file_hash).css({opacity: 1});
            }
        });
    })

    $('body').on('click', '.wcoa_delete_item', function (e) {
        e.preventDefault();
        const indexDelete = $(this).attr('data-item-index');
        fileQueue.splice(indexDelete, 1);
        renderFileList();
    });

    $('#wcoa_add_file').on('click', function (e) {
        e.preventDefault();
        $('#wcoa_files').trigger('click');
    });


    /* Based on mpen @ https://stackoverflow.com/a/14919494 */
    function formatFileSize(bytes) {
        if (Math.abs(bytes) < 1000) {
            return bytes + ' B';
        }
        const units = ['kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        var unit = -1;
        do {
            bytes /= 1000;
            unit++;
        } while (Math.round(Math.abs(bytes) * 10) / 10 >= 1000 && unit < units.length - 1);
        return bytes.toFixed(1) + ' ' + units[unit];
    }

    function showMessage(message) {
        if (message !== '') {
            $('#wcoa_message').html('<p>' + message + '</p>');
        } else {
            $('#wcoa_message').html('');
        }
    }

    function updateProgressbar(value, isVisible = true) {
        $('#wcoa_progress').toggle(isVisible);
        $('#wcoa_progress .bar').css({'width': value + '%'}).text(value + '%');
    }

})(jQuery);