/* *****************************************************

Project: Horn - WHMCS Dashboard Hosting Theme
Description: Horn Responsive Premium Theme Designed for all web hosting providers
Author: inebur (Rúben Rodrigues)
Author URI: http://inebur.com/
Author Envato: https://themeforest.net/user/inebur
Copyright: 2022 inebur
Version: 1.9

[Main Javascript]
***************************************************** */
document.addEventListener('DOMContentLoaded', function() {
    "use strict";
    loader();
    openmenu();
    sidemenu();
    togglemenu();
    gravatar();
    settings();
});

/*-- SVG Change color style --*/
$("img.svg")
.each(function() {
    var $img = jQuery(this);
    var attributes = $img.prop("attributes");
    var imgURL = $img.attr("src");
    $.get(imgURL, function(data) {
        var $svg = $(data)
        .find('svg');
        $svg = $svg.removeAttr('xmlns:a');
        $.each(attributes, function() {
            $svg.attr(this.name, this.value);
        });
        $img.replaceWith($svg);
    });
});

/*-- Styleswitch color style --*/
(function($) {
    $(document).ready(function() {
        $(".styleswitch").click(function() {
            switchStylestyle(this.getAttribute("data-rel"));
            return false
        });
    });
    function switchStylestyle(styleName) {
        $("link[rel*=style][title]").each(function(i) {
            this.disabled = true;
            if (this.getAttribute("title") == styleName) this.disabled = false
        })
    }
})(jQuery);

/*-- Settings Dropdown Menu --*/
$('.dropdown-menu').click(function(e) {
    e.stopPropagation();
});

/*-- Active Menu --*/
jQuery(function($) {
    var path = window.location.href;
    $('.navbar ul li a')
    .each(function() {
        if (this.href === path) {
            $(this)
            .addClass('active');
            $(this)
            .parent()
            .parent()
            .closest("li")
            .addClass('active2');
            $('.active2 a:first')
            .addClass('active');
        }
    });
});

/*-- Loader --*/
function loader() {
    $(window).on('load', function() {
        $("#spinner-area").fadeOut("slow");
    })
}

/*-- Open Menu */
function openmenu() {
    var e = $(window)[0].innerWidth;
    0 == $("nav.navbar").hasClass("top-menu") && (e <= 1200 && e >= 992 && $("nav.navbar").addClass("navbar-collapsed"),
        e < 992 && $("nav.navbar").removeClass("navbar-collapsed")), (e <= 1200 && e >= 992 && $("#footer").addClass("footer-collapsed"),
        e < 992 && $("#footer").removeClass("footer-collapsed"))
}

/*-- Side Menu --*/
function sidemenu() {
    $(window)[0].innerWidth < 768 ? setTimeout(function() {
        $(".sidenav-menu")
            .addClass("sidenav-menu-dis")
            .removeClass("sidenav-menu"),
            $(".top-menu").
        addClass("top-menu-dis")
            .removeClass("top-menu")
    }, 400) : setTimeout(function() {
        $(".sidenav-menu-dis").addClass("sidenav-menu").removeClass("sidenav-menu-dis"), $(".top-menu-dis").addClass("top-menu").removeClass("top-menu-dis")
    }, 400)
}

/*-- toggle menu --*/
function togglemenu() {
    $(document).ready(function() {
            function e(e) {
                13 == e.which && s(e)
            }
            openmenu(), sidemenu();
            var o = $(window)[0].innerWidth;
            if ($('[data-toggle="tooltip"]').tooltip(),
                $(".mobmenu").on("click", function() {
                    $(this).toggleClass("on")
                }),
                $("#mobcol").on("click", function() {
                    $("nav.navbar").toggleClass("navbar-collapsed")
                    $("#footer").toggleClass("footer-collapsed")
                }),
                $(".search-btn").on("click", function() {
                    $(this);
                    $(".main-search").addClass("open"), $(".main-search .form-control").css({
                        width: "90px"
                    })
                }),
                $(".search-close").on("click", function() {
                    $(this);
                    $(".main-search").removeClass("open"), $(".main-search .form-control").css({
                        width: "0"
                    })
                }),
                !$("nav.navbar").hasClass("top-menu")
            ) {
                var o = $(window)[0].innerWidth;
                o < 992 || $("nav.navbar").hasClass("menupos-static") ? $(".navbar-content").slimScroll({
                    setTop: "1px",
                    size: "2px",
                    wheelStep: 10,
                    touchScrollStep: 90,
                    alwaysVisible: !1,
                    allowPageScroll: !0,
                    color: "rgba(0,0,0,0.3)",
                    height: "calc(100% - 10px)",
                    width: "100%"
                }) : $(".navbar-content").slimScroll({
                    setTop: "1px",
                    size: "2px",
                    wheelStep: 10,
                    touchScrollStep: 90,
                    alwaysVisible: !1,
                    allowPageScroll: !0,
                    color: "rgba(0,0,0,0.3)",
                    height: "calc(100vh - 70px)",
                    width: "100%"
                })
            }
        }),
        /* vertical menu functions  */
        $(window).resize(function() {
            openmenu(), sidemenu()
        });
    var ost = 0;
}
$.fn.menufullstructure = function(e) {
    var s = (this.attr("id"), {
            themelayout: "vertical",
            MenuTrigger: "click",
            SubMenuTrigger: "click"
        }),
        e = $.extend({}, s, e),
        d = {
            menustructure: function() {
                d.sidebarinner(), d.submenutrigger(), d.HandleOffset()
            },
            submenutrigger: function() {
                var s = $(window),
                    d = s.width();
                if (1 == $("nav.navbar").hasClass("top-menu"))
                    if (d >= 768) {
                        var i = $(".inner-navbar .submenu > li.hasmenu");
                        i.off("click").off("mouseenter mouseleave").hover(function() {
                            $(this).addClass("trigger")
                        }, function() {
                            $(this).removeClass("trigger")
                        })
                    } else {
                        var i = $(".inner-navbar .submenu > li > .submenu > li");
                        i.off("mouseenter mouseleave").off("click").on("click", function() {
                            0 === $(this).closest(".submenu").length ? $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".submenu > li > .submenu > li.trigger").children(".submenu").slideUp(), $(this).closest(".inner-navbar").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown()) : $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".submenu > li > .submenu > li.trigger").children(".submenu").slideUp(), $(this).closest(".submenu").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown())
                        }), $(".inner-navbar .submenu > li > .submenu > li").on("click", function(e) {
                            e.stopPropagation(), alert("click call"), 0 === $(this).closest(".submenu").length ? $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".hasmenu li.trigger").children(".submenu").slideUp(), $(this).closest(".inner-navbar").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown()) : $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".hasmenu li.trigger").children(".submenu").slideUp(), $(this).closest(".submenu").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown())
                        })
                    }
                switch (e.SubMenuTrigger) {
                    case "click":
                        $("nav.navbar .hasmenu").removeClass("is-hover"), $(".inner-navbar .submenu > li > .submenu > li").on("click", function(e) {
                            e.stopPropagation(), 0 === $(this).closest(".submenu").length ? $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".submenu > li > .submenu > li.trigger").children(".submenu").slideUp(), $(this).closest(".inner-navbar").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown()) : $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".submenu > li > .submenu > li.trigger").children(".submenu").slideUp(), $(this).closest(".submenu").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown())
                        }), $(".submenu > li").on("click", function(e) {
                            e.stopPropagation(), 0 === $(this).closest(".submenu").length ? $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".hasmenu li.trigger").children(".submenu").slideUp(), $(this).closest(".inner-navbar").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown()) : $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($(".hasmenu li.trigger").children(".submenu").slideUp(), $(this).closest(".submenu").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown())
                        })
                }
            },
            sidebarinner: function() {
                var s = $(window),
                    d = s.width();
                if (1 == $("nav.navbar").hasClass("top-menu")) {
                    var i = $(".inner-navbar > li");
                    d >= 768 ? i.off("click").off("mouseenter mouseleave").hover(function() {
                        if ($(this).addClass("trigger"), $(".submenu", this).length) {
                            var e = $(".submenu:first", this),
                                s = e.offset(),
                                d = s.left,
                                i = e.width();
                            $(window).height();
                            if (d + i <= $(window).width()) $(this).removeClass("edge");
                            else {
                                var o = $(".sidenav-inner").attr("style");
                                $(".sidenav-inner").css({
                                    "margin-left": parseInt(o.slice(12, o.length - 3)) - 80
                                }), $(".sidenav-horizontal-prev").removeClass("disabled")
                            }
                        }
                    }, function() {
                        $(this).removeClass("trigger")
                    }) : i.off("mouseenter mouseleave").off("click").on("click", function() {
                        $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($("li.trigger").children(".submenu").slideUp(), $(this).closest(".inner-navbar").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown())
                    })
                }
                switch (e.MenuTrigger) {
                    case "click":
                        $("nav.navbar").removeClass("is-hover"), $(".inner-navbar > li:not(.menu-caption) ").on("click", function() {
                            $(this).hasClass("trigger") ? ($(this).removeClass("trigger"), $(this).children(".submenu").slideUp()) : ($("li.trigger").children(".submenu").slideUp(), $(this).closest(".inner-navbar").find("li.trigger").removeClass("trigger"), $(this).addClass("trigger"), $(this).children(".submenu").slideDown())
                        })
                }
            },
            HandleOffset: function() {
                switch (e.themelayout) {
                    case "horizontal":
                        "hover" === e.SubMenuTrigger ? $("li.hasmenu").on("mouseenter mouseleave", function(e) {
                            if ($(".submenu", this).length) {
                                var s = $(".submenu:first", this),
                                    d = s.offset(),
                                    i = d.left,
                                    o = s.width();
                                $(window).height();
                                i + o <= $(window).width() ? $(this).removeClass("edge") : $(this).addClass("edge")
                            }
                        }) : $("li.hasmenu").on("click", function(e) {
                            if (e.preventDefault(), $(".submenu", this).length) {
                                var s = $(".submenu:first", this),
                                    d = s.offset(),
                                    i = d.left,
                                    o = s.width();
                                $(window).height();
                                i + o <= $(window).width() || $(this).toggleClass("edge")
                            }
                        })
                }
            }
        };
    d.menustructure()
}

$('.header-main-slider').flickity({
  prevNextButtons: false,
  pageDots: true,
  autoPlay: 8000,
  fade: true
});
$('.header-main-nav').flickity({
  asNavFor: '.header-main-slider',
  prevNextButtons: false,
  pageDots: false,
  contain: true
  
});
$('.banner-slider').flickity({
  prevNextButtons: false,
  pageDots: true,
});

/* Gravatar Email */
function gravatar() {
    $(document).ready(function(){
        if ($('#gravataremail').length) {
        var email = document.getElementById('gravataremail').innerText;
        if(email != ''){
            var imgUrl = 'https://gravatar.com/avatar/'+MD5(email)+'';
            $.ajax({
                url:imgUrl,
                type:"HEAD",
                crossDomain:true,
                success:function(){
                    $(".gravatar").attr("src",imgUrl);
                }
            });
        }
        }
    });
}

/* Settings */
function settings() {
    jQuery(document).ready(function($) {

        $(".color-changer a").on("click", function(e) {
            if (!$(this).hasClass("disable")) {
                e.preventDefault();
                var color = $(this).attr("href");
                $(".color-changer a").removeClass("active");
                $(this).addClass("active");
            }
        });

        $(".set-modes a").on("click", function(e) {
            if ($(this).hasClass('active')) {
                $(this).removeClass("active");
            } else {
                $(this).addClass("active");
            }
        });

        $(".setstick").click(function() {
            if ($(".setstick").hasClass("active")) {
                $("body").addClass('bsticky-header');
                $("body").removeClass('bnotsticky-header');
            } else {
                $("body").removeClass('bsticky-header');
                $("body").addClass('bnotsticky-header');
            }
        });

        $(".barmenu").click(function() {
            if (!$(".barmenu").hasClass("active")) {
                $(".mobmenu").addClass('on');
                $(".navbar").removeClass('navbar-collapsed');
                $("#footer").removeClass('footer-collapsed');
            } else {
                $(".mobmenu").removeClass('on');
                $(".navbar").addClass('navbar-collapsed');
                $("#footer").addClass('footer-collapsed');
            }
        });

        
        $(".navbar-content").hover(function() {
            $(".navbar").removeClass('navbar-collapsed');
            $("#footer").removeClass('footer-collapsed');
            $(".mobmenu").addClass("on");
        });

        $(".mobmenu").on("click", function(e) {
            if ($(this).hasClass("on")) {
                $(".barmenu").addClass('active');
            } else {
                $(".barmenu").removeClass('active');
            }
        });
        $(".navoff").click(function() {
            if (!$(".navoff").hasClass("active")) {
                $("body").removeClass('body-without-header');
            } else {
                $("body").addClass('body-without-header');
            }
        });
    });
}

/* Menu Not Collapsed */
$("#menu").menufullstructure({
    MenuTrigger: "click",
    SubMenuTrigger: "click"
}),

$("#mobcol,#mobcols").click(function(e) {
    $(window)[0].innerWidth < 992 && ($("nav.navbar").toggleClass("mob-open"), e.stopPropagation())
}),

$(window).ready(function() {
    var e = $(window)[0].innerWidth;
    $("nav.navbar").on("click tap", function(e) {
            e.stopPropagation()
        }), $(".main-container,.header").on("click", function() {
            e < 992 && 1 == $("nav.navbar").hasClass("mob-open") && ($("nav.navbar").removeClass("mob-open"), $("#mobcol,#mobcols").removeClass("on"))
        }),
        $("#mobile-header").on("click", function() {
            $(".navbar-collapse,.m-header").slideToggle()
        })
});

/* Menu Collapsed */
var MenuOpenTimers = {}
$('.hasmenu').each(function() {
    $(this).attr(
        'data-username',
        Math.random()
        .toString(36)
        .substring(3),
    )
})

/* AOS Scroll Effect */
AOS.init();
/* 💚 CloudOn — LED μέσα στο εικονίδιο VPS */
(function(){
  var SVGNS='http://www.w3.org/2000/svg';
  // θέσεις (viewBox 0 0 32 32): δεξιά σε κάθε μονάδα + ένα δεύτερο λαμπάκι δίπλα
  var SPOTS=[[24.7,6.15,'.15s','1.4s'],[22.4,6.15,'.5s','.9s'],[24.7,19.24,'.3s','1.9s'],[22.4,19.24,'0s','1.1s']];
  function isVps(svg){
    if(svg.dataset.cnpLed)return false;
    var vb=svg.getAttribute('viewBox')||'';
    var t=svg.querySelector('title');
    return vb==='0 0 32 32' || (t&&/vps/i.test(t.textContent));
  }
  function inject(svg){
    svg.dataset.cnpLed='1';
    SPOTS.forEach(function(s){
      var c=document.createElementNS(SVGNS,'circle');
      c.setAttribute('cx',s[0]); c.setAttribute('cy',s[1]); c.setAttribute('r','0.95');
      c.setAttribute('class','cnp-led');
      c.style.animationDelay=s[2]; c.style.animationDuration=s[3];
      svg.appendChild(c);
    });
  }
  function scan(){
    document.querySelectorAll('.plan-content .prod-desc-div svg, .plan-content .prod-desc svg').forEach(function(svg){
      if(isVps(svg))inject(svg);
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);else scan();
  try{var mo=new MutationObserver(function(){clearTimeout(window._cnpLedT);window._cnpLedT=setTimeout(scan,120);});
    mo.observe(document.body||document.documentElement,{childList:true,subtree:true});}catch(e){}
  setTimeout(scan,800);setTimeout(scan,2000);
})();
