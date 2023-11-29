<?php
namespace FormLogic\FormCompletionCheck;

use \ExternalModules\AbstractExternalModule;

class FormCompletionCheck extends AbstractExternalModule
{
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {   
        $this->form_completion_check($instrument);      
    }

    function form_completion_check(string $instrument): void
    {
        $complete_element_json = json_encode($instrument."_complete");
        $fields = $this->getFieldNames($instrument);

        global $Proj;
        
        $checking_fields = [];
        
        $settings = $this->getProjectSettings('');
        
        if ($settings['fields_by_name']==true)
        {
            $check_key = $settings['field_prefix'];
            foreach($fields as $field)
            {
                if(strpos($field, $check_key)===0)
                {
                    array_push($checking_fields, $field);
                }
            }
        }
        if ($settings['fields_by_tag']==true)
        {
            foreach($fields as $field)
            {
                if(strpos($Proj->metadata[$field]['misc'], '@COMPLETIONCHECK')!==false)
                {
                    array_push($checking_fields, $field);
                }
            }
        }
        $checking_fields = array_unique($checking_fields);

        ?>
        <script type="text/javascript">
            let checkingFields = <?php echo json_encode($checking_fields); ?>;
            console.log("checkingFields: " + checkingFields);
            let WarningFields = [];
            checkingFields.forEach(function(field)
            {
                console.log("warning field name " + field);
                var element = document.querySelector('[sq_id="' + field + '"]');
                if(element)
                {
                    console.log("warning field " + field + " is found.");
                    WarningFields.push(element);
                }
            })
            function completion_check()
            {
                var nError = 0;
                var complete_elementName = <?php echo $complete_element_json; ?>;
                var completeElement = document.querySelector("[name='" + complete_elementName +"']");

                console.log(complete_elementName);
                if (completeElement)
                {
                    // checking for warning fields being displayed
                    var nError = 0;
                    WarningFields.forEach(function(field)
                    {
                        if (field) 
                        {
                            var display = field.style.display;
                            console.log("warning field style:" + field.textContent + "," + display);
                            if (display !== 'none') 
                            {
                                nError++;
                            }
                        }
                    });
                    console.log("Data check: found " + nError + " warning fields.");

                    // Text for completion
                    var complete_text_element = document.querySelector("div[data-mlm-field='" + complete_elementName + "'][data-mlm-type='label']");

                    if (nError===0)
                    {
                        complete_text_element.style.color = '';
                        complete_text_element.textContent = "Completed!";                       
                    }
                    else
                    {   
                        var plura = "";
                        if (nError > 1)
                        {
                            plura = "s";
                        }
                        complete_text_element.style.color = 'red';
                        complete_text_element.textContent = "Form not complete! " + nError 
                                                            + " error" + plura + " to solve.";
                    }
                    // if form not set to unverified, automatically set completion status fields
                    if(completeElement.value != 1)
                    {
                        if (nError===0)
                        {
                            completeElement.value = '2';
                        }
                        else
                        {
                            completeElement.value = '0'; 
                        }
                    }
 
                }
            }
            document.addEventListener("DOMContentLoaded",completion_check);

            document.addEventListener("DOMContentLoaded", function()
            {
                if (WarningFields.length>0)
                {
                    var OnWarningChange = function(mutationsList, observer)
                    {
                        for(var mutation of mutationsList)
                        {
                            if (mutation.type === 'attributes' && mutation.attributeName === 'style') 
                            {
                                console.log('The ' + mutation.attributeName + ' attribute was modified on', mutation.target);
                                completion_check();
                            }
                        }
                    };
                    var observerOptions = {
                            attributes: true, // Listen for attribute changes
                            attributeFilter: ['style'] // Only listen for changes to the 'style' attribute
                        };                    var observer = new MutationObserver(OnWarningChange);
                    WarningFields.forEach(function(field)
                    {
                        observer.observe(field, observerOptions);
                    });

                    // check complete when user changes the completion status
                    var complete_elementName = <?php echo $complete_element_json; ?>;
                    var completeElement = document.querySelector("[name='" + complete_elementName +"']");

                    completeElement.addEventListener('change',completion_check);
                }
            });
        </script>
        <?php 
    }
}
?>
