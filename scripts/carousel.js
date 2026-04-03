$(function() {
    var $containers = $('.carousel-container');
    var total = $containers.length;

    function setPositions(currentIndex, animate) {
        if (animate === false) {
            $containers.addClass('no-transition');
        }
        $containers.each(function() {
            var $item = $(this);
            var idx = parseInt($item.attr('data-index'), 10);
            if (idx === currentIndex) {
                $item.attr('data-current', 'true').css('left', '0');
            } else if (idx === (currentIndex + 1) % total) {
                $item.attr('data-current', '').css('left', '105%');
            } else if (idx === (currentIndex - 1 + total) % total) {
                $item.attr('data-current', '').css('left', '-105%');
            } else {
                $item.attr('data-current', '').css('left', '105%');
            }
        });
        if (animate === false) {
            setTimeout(function() {
                $containers.removeClass('no-transition');
            }, 20);
        }
    }

    $containers.each(function(index) {
        $(this).attr('data-index', index);
    });

    setPositions(0);

    function slide(direction) {
        var $current = $('.carousel-container[data-current="true"]');
        var currentIndex = parseInt($current.attr('data-index'), 10);
        var nextIndex = (currentIndex + (direction === 'next' ? 1 : -1) + total) % total;
        var $target = $('.carousel-container[data-index="' + nextIndex + '"]');

        $target.css('left', direction === 'next' ? '105%' : '-105%');
        $target[0].offsetHeight;

        $current.css('left', direction === 'next' ? '-105%' : '105%');
        $target.css('left', '0');

        setTimeout(function() {
            setPositions(nextIndex, false);
        }, 500);
    }

    $('.carousel-arrow').click(function() {
        slide($(this).hasClass('right') ? 'next' : 'prev');
    });

    var touchStartX = null;
    var minSwipeDistance = 50;
    $('#carousel').on('touchstart', function(e) {
        if (e.originalEvent.touches && e.originalEvent.touches.length === 1) {
            touchStartX = e.originalEvent.touches[0].clientX;
        }
    }).on('touchmove', function(e) {
        // prevent horizontal page scroll while swiping
        if (touchStartX !== null) {
            var touchCurrentX = e.originalEvent.touches[0].clientX;
            if (Math.abs(touchCurrentX - touchStartX) > 10) {
                e.preventDefault();
            }
        }
    }).on('touchend', function(e) {
        if (touchStartX === null) return;
        var touchEndX = e.originalEvent.changedTouches[0].clientX;
        var diff = touchEndX - touchStartX;
        if (Math.abs(diff) > minSwipeDistance) {
            if (diff < 0) {
                slide('next');
            } else {
                slide('prev');
            }
        }
        touchStartX = null;
    });
});