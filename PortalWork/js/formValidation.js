// this file handles custom form validation to work within customized form fields
(function () {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')

    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
                scrollToInvalid();
            }, false)
        })
})()
function scrollToInvalid() {
    $(".invalid-feedback:visible:first").each(function(){
        $('html, body').animate({
            scrollTop: $(this).focus().offset().top - 425
        }, 100);
    })
}

$(document).ready(function ()
{
    // validate checkboxes
    $(".form-field-checkboxes").each( function ()
    {
        validateCheck($(this));
    })

    // listener for checkboxes
    $(document).on('change','.checkbox',function(){
        validateCheck($(this));
    });

    // validate text areas
    $(".form-field-textarea").each( function ()
    {
        validateTextbox($(this));
    })

    //listener for text areas
    $(document).on('change input','.note-editable',function(){
        validateTextbox($(this));
    })

    // validate file fields
    $('.form-field-file').each( function ()
    {
        validateFileField($(this));
    })
});

//This listens to any uploads and sends the file to the server
$(document).on('change','.fileField', function(){

    //Get File data
    let id=$(this).attr('id');
    let name=$(this).attr('name').replace("f_","");
    let file_data = $('#'+id)[0].files[0];
    let formData = new FormData();
    formData.append(name, file_data);
    formData.append(name, id);
    let fileHolder = $(this).closest('.form-field').find('.file-holder');
    let filePath = $(this).val();
    let fileName = filePath.split("\\").pop();

    fileHolder.find('.no-files').hide();
    fileHolder.append('<div class="uploading-text" id="holder_' + name +'">Uploading '+ fileName+ ' <i class="fas fa-spinner fa-spin"></i> </div>');

    $(this).val('');



    //Send File to server
    $.ajax({
        url: '/file/ajaxUpload',
        type: 'POST',
        dataType: 'json',
        data: formData,
        processData: false,
        contentType: false,
        beforeSend:function(jqXHR){
            /* add url property and get value from settings (or from caturl)*/
            jqXHR.fileName = file_data.name;
        },
        success: function (data) {

            let uploadingTextElements = document.getElementsByClassName("uploading-text");
            let firstUploadingTextElement = uploadingTextElements.item(0)
            let fileName  = firstUploadingTextElement.innerText.split("Uploading ")[1];

            if(data.status === "success"){
                let checkHTML = `<div class="file">
												<p class="file-name"> ${data.oldFileName} <i class="icon-button delete-file-icon fa-solid fa-trash"></i> <span class="text-success"> File Uploaded Successfully</span</p>
												<input type="hidden" class="file_uploaded" name="${data.attributeId}[]" id=file1 value="${data.newFileName}|${data.oldFileName}">
											</div> `;
                $('#holder_' + data.attributeId).replaceWith(checkHTML);
                //fileHolder.find('.uploading-text').last().remove(); // remove only the last uploading text div
                //fileHolder.append(checkHTML);
                $('#f_'+ data.attributeId).removeAttr('required');
            }
            else
            {
                let failedHtml =    `<div class="file">
                                    <p class="file-name"> ${fileName} <i class="icon-button delete-file-icon fa-solid fa-trash"></i> <span class="text-danger"> File Failed to Upload! Error: ${data.message} </span> </p>
                                    <!-- <p class="file-name"> File Failed to Upload! Error: ${data.message} </p> -->
                                </div> `;
                $(firstUploadingTextElement).replaceWith(failedHtml);
            }
        },
        error: function (jqXHR) {
            //console.log("error function => ");
            //console.log(jqXHR);
            let uploadingTextElements = document.getElementsByClassName("uploading-text");
            let firstUploadingTextElement = uploadingTextElements.item(0)
            let fileName  = firstUploadingTextElement.innerText.split("Uploading ")[1];

            let failedHtml =    `<div class="file">
                                    <p class="file-name"> ${fileName} <i class="icon-button delete-file-icon fa-solid fa-trash"></i> <span class="text-danger"> File Failed to Upload! Error: ${jqXHR.statusText} </span>  </p>
                                    <!-- <p class="file-name"> File Failed to Upload! Error: ${jqXHR.statusText} </p> -->
                                </div> `;

            $(firstUploadingTextElement).replaceWith(failedHtml);
        }
    });


});
//This listens to the entire document for any 'clicks', and the following function occurs if that click was on a .delete-file-icon
$(document).on('click','.delete-file-icon', function(){

    let fileHolder = $(this).closest('.file-holder');
    let fileName = $(this).closest('.file').find('.file_uploaded').val();
    let fileInput = $(this).closest('.form-field-file').find("input.fileField");

    let xhr = new XMLHttpRequest();
    xhr.open("POST", '/dev/fileuploadTest/form/DeleteFile-ServerSide.php', true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send("fileName=" + fileName);

    $(this).closest('.file').remove();

    $numFiles = fileHolder.find('.file').length;
    if($numFiles === 0)
    {
        fileHolder.find('.no-files').show();
        if (fileInput.data("required")===true)
        {
            fileInput.prop("required", "true");
        }
    }
});

function validateFileField(fieldDiv)
{
    let holder = $(fieldDiv).find('.file-holder');
    let inputRequired = $(fieldDiv).find('.fileField[data-required="true"]');
    let files = $(holder).find('.file');

    if (files.length > 0 )
    {
        inputRequired.removeAttr('required');
    }
}

function validateCheck(check)
{
    let fieldDiv = $(check).closest(".form-field");
    let checkRequired = $(fieldDiv).find(".check-required");
    let checkedBox = $(fieldDiv).find(".check-required:input:checked");

    if (checkedBox.length > 0)
    {
        $(checkRequired).each(function ()
        {
            $(this).removeAttr('required');
        });
    }
    else
    {
        if (checkRequired != null)
        {
            $(checkRequired).prop("required", "true");
        }
    }
}

function validateTextbox(text){
    let area = $(text).closest(".form-field-textarea");
    let classArray = $(area).attr("class").split(" ");
    let targetAttr = classArray.find(findFieldAttr);

    //console.log("initial");
    //console.log(text);
    //console.log(area);

    function findFieldAttr(value, index, array) {
        if (array[index].includes("form-field-attr")) {
            return value;
        }
    }

    let textAreaBox = $("."+targetAttr+" .wysiwyg");

    let invalid = (textAreaBox.summernote('isEmpty') && textAreaBox.prop('required') === true);
    if (invalid){
        if($("."+targetAttr).find('.valid-feedback').is(":visible"))
        {
            $("."+targetAttr).find('.invalid-feedback').show();
            $("."+targetAttr).find('.valid-feedback').hide();
        }
    }
    else{
        if($("."+targetAttr).find('.invalid-feedback').is(":visible"))
        {
            $("."+targetAttr).find('.invalid-feedback').hide();
            $("."+targetAttr).find('.valid-feedback').show();
        }
    }
}