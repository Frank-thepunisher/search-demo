(function () {
    var MODE_CATEGORY = 1,
        MODE_SEARCH = 2;

    var mode = MODE_CATEGORY,
        keywords = null,
        pages = null,
        page = 1,
        categoryId = null;

    function search() {
        var deferred = $.Deferred(),
            params = {
                page: page
            };

        if (mode === MODE_CATEGORY) {
            params.category = categoryId;
        } else {
            params.keywords = keywords.join(',');
        }

        $('.result-container').addClass('loading').find('.images').empty();

        $.ajax({
            url: 'search.php',
            data: params
        }).done(function (data, status, jqxhr) {
            var imageContainer = $('.result-container .images');
            pages = data.meta.pages;
            data.images.forEach(function (image) {
                $('<div>').attr({
                    title: image.description,
                    'data-id': image.id,
                    'data-preview': image.preview
                }).css({
                    backgroundImage: 'url(' + image.thumbnail + ')'
                }).appendTo(imageContainer);
            });

            if (!data.images.length) {
                $('<span>').text('Die Suche lieferte keine Ergebnisse.').appendTo(imageContainer);
            }

        }).fail(function (jqxhr, status, error) {
            deferred.reject(JSON.parse(jqxhr.responseText).error);
        }).always(function () {
            $('.result-container').removeClass('loading');
        });

        return deferred.promise();
    }

    $(document).ready(function () {
        var previewEl = $('<div><img></div>').css('position', 'absolute');

        $('.result-container').on('mousemove', '.images div', function (event) {
            previewEl.css({
                left: event.pageX + 15,
                top: event.pageY + 15
            });
        }).on('mouseleave', 'div', function (event) {
            previewEl.detach();
        }).on('mouseenter', 'div', function (event) {
            var previewUrl = $(event.target).attr('data-preview');
            previewEl.appendTo('body').find('img').attr('src', previewUrl);
        });
    });

    $(document).ready(function () {
        var primary = $('.categories select.primary'),
            secondary = $('.categories select.secondary'),
            tertiary = $('.categories select.tertiary');

        function categorySearch(id) {
            categoryId = id;
            mode = MODE_CATEGORY;
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
            var subcategories = $(this).children(':selected').data().categories;

            secondary.empty();
            tertiary.empty().toggle(subcategories[0].hasOwnProperty('categories'));

            subcategories.forEach(function (category) {
                $('<option>').text(category.name).data(category).appendTo(secondary);
            });
            secondary.trigger('change');
        });

        secondary.change(function () {
            var category = $(this).children(':selected').data(),
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
            var category = $(this).children(':selected').data();
            categorySearch(category.id);
        });
    });

    $(document).ready(function () {
        $('button.previous').click(function () {
            if (page > 1) {
                page--;
                search();
            }
        });

        $('button.next').click(function () {
            if (page < pages) {
                page++;
                search();
            }
        });

        $('.search button').click(function () {
            page = 1;
            keywords = $('.search input').val().split(/\s+/).filter(function (term) {
                return term.length > 0;
            });
            mode = MODE_SEARCH;
            search();
        });
        $('.search input').keydown(function (event) {
            if (event.which === 13) {
                $('.search button').click();
            }
        });
    });
})();
