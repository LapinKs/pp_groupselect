define(['jquery'], function($) {
    return {
        init: function() {
            var groupCount = 1;

            
            $('#id_addgroupbutton').click(function() {
                groupCount++;
                var newGroupHtml = `
                    <div id="group_${groupCount}">
                        <h3>${M.util.get_string('groupname', 'qtype_ddingroups', groupCount)}</h3>
                        <input type="text" name="groupname_${groupCount}" placeholder="Group name">
                        <div id="checkboxes_container_${groupCount}" class="checkboxes-container"></div>
                    </div>`;
                $('#id_addgroupbutton').before(newGroupHtml);
            });

           
            $('[name^="answer"]').on('input', function() {
                var answers = $('[name^="answer"]').map(function() {
                    return $(this).val().trim();
                }).get();

                $('.checkboxes-container').each(function() {
                    var container = $(this);
                    container.empty();

                    answers.forEach(function(answer, index) {
                        if (answer) {
                            container.append(`
                                <label>
                                    <input type="checkbox" name="checkbox_${container.attr('id')}_${index + 1}">
                                    ${index + 1}
                                </label>
                            `);
                        }
                    });
                });
            });
        }
    };
});
