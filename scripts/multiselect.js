$(document).ready(function() {
    $(".multiselector").each(function() {
        var previous = [];
        var $select = $(this);
        var $container = $select.prev(".multiselect_container");
        var $selected = $container.find(".multiselect_selected");
        var $options = $container.find(".multiselect_options");
        var $search = $options.find(".multiselect_search");
        var $list = $options.find(".multiselect_options_list");

        // Populate the options list
        $select.find("option").each(function() {
            var $option = $(this);
            var value = $option.val();
            var text = $option.text();
            var isSelected = $option.is(":selected");
            var listItem = $("<li>").text(text).attr("data-value", value);
            if (isSelected) {
                listItem.addClass("selected");
            }
            $list.append(listItem);
        });

        updateSelectedText($select, $container);

        // Filter options based on search input
        $search.on("input", function() {
            var searchTerm = $(this).val().toLowerCase();
            $list.find("li").each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(searchTerm) > -1);
            });
        });

        // Handle option selection
        $list.on("click", "li", function() {
            var value = $(this).data("value");
            reorderSelect($select, value);
            var isSelected = $(this).hasClass("selected");
            if (isSelected) {
                $(this).removeClass("selected");
                $select.find(`option[value="` + value + `"]`).prop("selected", false).attr("selected", false);
            } else {
                $(this).addClass("selected");
                $select.find(`option[value="` + value + `"]`).prop("selected", true).attr("selected", true);
            }
            updateSelectedText($select, $container);
            $(".multiselect_remove_selected_option").off("click").on("click", function () {
                let value = $(this).data("value");
                $list.find(`li[data-value="${value}"]`).click();
            });
        });

        // Handle selection removal
        $(".multiselect_remove_selected_option").off("click").on("click", function () {
            let value = $(this).data("value");
            $list.find(`li[data-value="${value}"]`).click();
        });

        // Update selected text and count
        function updateSelectedText($select, $container) {
            var selectedOptions = $select.find("option:selected");
            var count = selectedOptions.length;
            if (count === 0) {
                $container.find(".multiselect_selected_text").text("");
            } else {
                var selectedTexts = selectedOptions.map(function() {
                    return `<div class="multiselect_selected_option">
                                <div class="multiselect_remove_selected_option" data-value="` + $(this).val() + `">x</div>
                                ` + $(this).text() + `
                            </div>`;
                }).get();
                $container.find(".multiselect_selected_text").html(selectedTexts);
            }
        }

        function reorderSelect($select, value) {
            var $option = $select.find(`option[value="${value}"]`);

            // Was this value just selected or deselected?
            if ($option.prop("selected")) {
                // Newly selected: move to the end of the selected group
                $option.appendTo($select);
            } else {
                // Deselected: move just after the selected group,
                // or to the top if nothing is selected.
                var $lastSelected = $select.find("option:selected").last();

                if ($lastSelected.length) {
                    $option.insertAfter($lastSelected);
                } else {
                    $option.prependTo($select);
                }
            }
        }
    });
});