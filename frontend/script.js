(function () {
    // We can either search by category (if an entry from the dropdowns was selected) or by keywords (if the user
    // entered something in the text box). The two are mutually exclusive, chosing one disables the other.
    var MODE_CATEGORY = 1,
        MODE_SEARCH = 2;

    var searchOptions = {
        mode: MODE_CATEGORY,
        page: 1,
        pages: null,
        keywords: null,
        categoryId: null
    };

    // Execute the search with the currently set options.
    function search() {
        var resultContainerEl = $('.result-container'),
            paginationEl = $('.pagination'),
            params = {
                page: searchOptions.page
            };

        if (searchOptions.mode === MODE_CATEGORY) {
            params.category = searchOptions.categoryId;
        } else {
            params.keywords = searchOptions.keywords.join(',');
        }

        resultContainerEl.addClass('loading').empty();

        $.ajax({
            url: 'search.php',
            data: params
        }).done(function (data) {
            searchOptions.pages = data.meta.pages;

            data.images.forEach(function (image) {
                $('<div>').attr({
                    'data-id': image.id,
                    'data-preview': image.preview
                }).css({
                    backgroundImage: 'url(' + image.thumbnail + ')'
                }).appendTo(resultContainerEl);
            });

            if (!data.images.length) {
                $('<span>').text('Die Suche lieferte keine Ergebnisse.').appendTo(resultContainerEl);
                paginationEl.hide();
            } else {
                paginationEl.show();
                paginationEl.find('.current').text(data.meta.page);
                paginationEl.find('.total').text(data.meta.pages);
            }
        }).fail(function (jqxhr) {
            var message = JSON.parse(jqxhr.responseText).error;
            alert('Es ist ein Fehler aufgetreten: ' + message);
        }).always(function () {
            $('.result-container').removeClass('loading');
        });
    }

    // Set up the mouseover preview element
    $(document).ready(function () {
        var previewEl = $('<div><img></div>').addClass('preview-image');

        $('.result-container').on('mousemove', 'div', function (event) {
            var previewUrl = $(event.target).attr('data-preview');

            // Append only if needed to prevent flickering.
            previewEl.filter(':not(:visible)').appendTo('body');
            previewEl.css({
                left: event.pageX + 15,
                top: event.pageY + 15
            }).children('img').attr('src', previewUrl);
        }).on('mouseleave', 'div', function () {
            previewEl.children('img').attr('src', null);
            previewEl.detach();
        });
    });

    // Wire up dropdown selection logic
    $(document).ready(function () {
        var primary = $('.categories select.primary'),
            secondary = $('.categories select.secondary'),
            tertiary = $('.categories select.tertiary');

        function categorySearch(id) {
            searchOptions.categoryId = id;
            searchOptions.mode = MODE_CATEGORY;
            $('.search input').val(null);
            search();
        }

        $.get('categories.php').done(function (data) {
            data.forEach(function (primaryCategory) {
                $('<option>').text(primaryCategory.name).data(primaryCategory).appendTo(primary);
            });
            primary.trigger('change');
        });

        primary.change(function () {
            var subcategories = primary.children(':selected').data().categories;

            secondary.empty();
            // Hide the third dropdown if the currently selected category has no subcategories.
            tertiary.empty().toggle(subcategories[0].hasOwnProperty('categories'));

            subcategories.forEach(function (category) {
                $('<option>').text(category.name).data(category).appendTo(secondary);
            });
            secondary.trigger('change');
        });

        secondary.change(function () {
            var category = secondary.children(':selected').data(),
                subcategories = category.categories;

            tertiary.empty();

            if (subcategories) {
                subcategories.forEach(function (category) {
                    $('<option>').text(category.name).data(category).appendTo(tertiary);
                });
                tertiary.change();
            } else {
                categorySearch(category.id);
            }
        });

        tertiary.change(function () {
            var category = tertiary.children(':selected').data();
            categorySearch(category.id);
        });
    });

    $(document).ready(function () {
        var inputEl = $('.search input'),
            searchButtonEl = $('.search button');

        $('button.previous').click(function () {
            if (searchOptions.page > 1) {
                searchOptions.page--;
                search();
            }
        });

        $('button.next').click(function () {
            if (searchOptions.page < searchOptions.pages) {
                searchOptions.page++;
                search();
            }
        });

        searchButtonEl.click(function () {
            searchOptions.page = 1;
            searchOptions.keywords = inputEl.val().split(/\s+/).filter(function (term) {
                return term.length > 0;
            });
            searchOptions.mode = MODE_SEARCH;
            search();
        });

        inputEl.keydown(function (event) {
            if (event.which === 13) {
                searchButtonEl.click();
            }
        });
    });
})();
