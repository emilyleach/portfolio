// this document controls the attributes of elements within forms in the portal
let editors = [];

window.draggingAceEditor = {};


function beautifyEditor(id)
{
    editors[id].setValue(JSON.stringify(JSON.parse(editors[id].getValue()),null,6))
}

$(document).ready(function(){

    const observer = new ResizeObserver(entries => {
        const [changed] = entries;
        console.log(changed.target.id);
        editors[changed.target.id].resize();
    })

    $('.editor').not('.prototype .editor').each(function( index ) { // this function initializes any editors
        let id = $(this).attr('id');
        editors[id] = ace.edit(this);
        editors[id].setOptions({
            fontSize: "15pt"
        });
        editors[id].session.setTabSize(8);

        let language = $('#' + id).data('language');

        editors[id].setTheme("ace/theme/monokai");

        editors[id].getSession().setMode('ace/mode/' + language);
        editors[id].setAutoScrollEditorIntoView(true);
        observer.observe(document.getElementById(id));


    });


    $('.wysiwyg').not('.prototype .wysiwyg').summernote({ height: 250 }); // this initializes wysiwyg text editors


    $(document).on('change keyup','.editor',function(){ // this responds to value changes in editors and updates values
        let id = $(this).attr('id');
        let values = editors[id].getValue();
        //console.log(values);
        let textId = id.replace('_editor','');
        $("#" + textId).val(values);
    });
    $(document).on('change','.checkbox',function(){ // this checks and unchecks checkboxes and updates the hidden field value to reflect
        let $hiddenId = $(this).attr('id').replace('_cb','');
        let $checkVal = $(this).prop('checked');
        $("#" + $hiddenId).val($checkVal);
    });

    $(document).on('change','.editor',function(){  // updates hidden values on editor changes
        let $hiddenId = $(this).attr('id').replace('_cb','');
        let $checkVal = $(this).prop('checked');
        $("#" + $hiddenId).val($checkVal);
    });

    $(document).on('click','.beautify-button',function(e){
        e.preventDefault();
        let editorId = $(this).attr('id').replace('_beautify','_editor');
        beautifyEditor(editorId);

    });


    updateAccordion(); // refreshes any visible accordions
    $('.spinner').hide();
    $('main').css('visibility','visible');


});
function updateAccordion(){
    $( ".accordion" )
        .accordion({
            header: "> div > h3",
            collapsible : true,
            active: false,
            heightStyle: "content"
        })
        .sortable({ // this function makes the accordion sortable
            axis: "y",
            handle: "h3",
            stop: function( event, ui ) {
                // IE doesn't register the blur when sorting
                // so trigger focusout handlers to remove .ui-state-focus
                ui.item.children( "h3" ).triggerHandler( "focusout" );

                // Refresh accordion to handle new order
                $( this ).accordion( "refresh" );
                $('.accordion .group').each(function( index ) {
                    $(this).find('input.order').val(index);
                });
            }
        });
    $('.spinner').hide();
    $('.attribute-list').show();
}