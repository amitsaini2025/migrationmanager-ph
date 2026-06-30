"use strict";

$(window).on("load", function () {
  $(".loader").fadeOut("slow");
});

// Initialize Feather Icons when DOM is ready
$(document).ready(function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    } else {
        console.error('Feather Icons library not loaded');
    }
});
// Global
$(function () {
  let sidebar_nicescroll_opts = {
    cursoropacitymin: 0,
    cursoropacitymax: 0.8,
    zindex: 892
  },
    now_layout_class = null;

  var sidebar_sticky = function () {
    if ($("body").hasClass("layout-2")) {
      $("body.layout-2 #sidebar-wrapper").stick_in_parent({
        parent: $("body")
      });
      $("body.layout-2 #sidebar-wrapper").stick_in_parent({ recalc_every: 1 });
    }
  };
  sidebar_sticky();

  var sidebar_nicescroll;
  var update_sidebar_nicescroll = function () {
    let a = setInterval(function () {
      if (sidebar_nicescroll != null) sidebar_nicescroll.resize();
    }, 10);

    setTimeout(function () {
      clearInterval(a);
    }, 600);
  };

  var sidebar_dropdown = function () {
    if ($(".main-sidebar").length) {
      $(".main-sidebar").niceScroll(sidebar_nicescroll_opts);
      sidebar_nicescroll = $(".main-sidebar").getNiceScroll();

      $(".main-sidebar .sidebar-menu li a.has-dropdown")
        .off("click")
        .on("click", function () {
          var me = $(this);

          me.parent()
            .find("> .dropdown-menu")
            .slideToggle(500, function () {
              update_sidebar_nicescroll();
              return false;
            });
          return false;
        });
    }
  };
  sidebar_dropdown();

  if ($("#top-5-scroll").length) {
    $("#top-5-scroll")
      .css({
        height: 315
      })
      .niceScroll();
  }
  if ($("#scroll-new").length) {
    $("#scroll-new")
      .css({
        height: 200
      })
      .niceScroll();
  }

  $(".main-content").css({
    minHeight: $(window).outerHeight() - 95
  });

  $(".nav-collapse-toggle").click(function () {
    $(this)
      .parent()
      .find(".navbar-nav")
      .toggleClass("show");
    return false;
  });

  $(document).on("click", function (e) {
    $(".nav-collapse .navbar-nav").removeClass("show");
  });

  var toggle_sidebar_mini = function (mini) {
    let body = $("body");

    if (!mini) {
      body.removeClass("sidebar-mini");
      $(".main-sidebar").css({
        overflow: "hidden"
      });
      setTimeout(function () {
        $(".main-sidebar").niceScroll(sidebar_nicescroll_opts);
        sidebar_nicescroll = $(".main-sidebar").getNiceScroll();
      }, 500);
      $(".main-sidebar .sidebar-menu > li > ul .dropdown-title").remove();
      $(".main-sidebar .sidebar-menu > li > a").removeAttr("data-toggle");
      $(".main-sidebar .sidebar-menu > li > a").removeAttr(
        "data-original-title"
      );
      $(".main-sidebar .sidebar-menu > li > a").removeAttr("title");
    } else {
      body.addClass("sidebar-mini");
      body.removeClass("sidebar-show");
      sidebar_nicescroll.remove();
      sidebar_nicescroll = null;
      $(".main-sidebar .sidebar-menu > li").each(function () {
        let me = $(this);

        if (me.find("> .dropdown-menu").length) {
          me.find("> .dropdown-menu").hide();
          me.find("> .dropdown-menu").prepend(
            '<li class="dropdown-title pt-3">' + me.find("> a").text() + "</li>"
          );
        } else {
          me.find("> a").attr("data-toggle", "tooltip");
          me.find("> a").attr("data-original-title", me.find("> a").text());
          $("[data-toggle='tooltip']").tooltip({
            placement: "right"
          });
        }
      });
    }
  };

  // sticky header toggle function
  var toggle_sticky_header = function (sticky) {
    if (!sticky) {
      $(".main-navbar")[0].classList.remove("sticky");
    } else {
      $(".main-navbar")[0].classList += " sticky";
    }
  };

  $('.menu-toggle').on('click', function (e) {
    var $this = $(this);
    $this.toggleClass('toggled');

  });

  $.each($('.main-sidebar .sidebar-menu li.active'), function (i, val) {
    var $activeAnchors = $(val).find('a:eq(0)');

    $activeAnchors.addClass('toggled');
    $activeAnchors.next().show();
  });

  $("[data-toggle='sidebar']").click(function () {
    var body = $("body"),
      w = $(window);

    if (w.outerWidth() <= 1024) {
      body.removeClass("search-show search-gone");
      if (body.hasClass("sidebar-gone")) {
        body.removeClass("sidebar-gone");
        body.addClass("sidebar-show");
      } else {
        body.addClass("sidebar-gone");
        body.removeClass("sidebar-show");
      }

      update_sidebar_nicescroll();
    } else {
      body.removeClass("search-show search-gone");
      if (body.hasClass("sidebar-mini")) {
        toggle_sidebar_mini(false);
      } else {
        toggle_sidebar_mini(true);
      }
    }

    return false;
  });

  var toggleLayout = function () {
    var w = $(window),
      layout_class = $("body").attr("class") || "",
      layout_classes =
        layout_class.trim().length > 0 ? layout_class.split(" ") : "";

    if (layout_classes.length > 0) {
      layout_classes.forEach(function (item) {
        if (item.indexOf("layout-") != -1) {
          now_layout_class = item;
        }
      });
    }

    if (w.outerWidth() <= 1024) {
      if ($("body").hasClass("sidebar-mini")) {
        toggle_sidebar_mini(false);
        $(".main-sidebar").niceScroll(sidebar_nicescroll_opts);
        sidebar_nicescroll = $(".main-sidebar").getNiceScroll();
      }

      $("body").addClass("sidebar-gone");
      $("body").removeClass("layout-2 layout-3 sidebar-mini sidebar-show");
      $("body")
        .off("click")
        .on("click", function (e) {
          if (
            $(e.target).hasClass("sidebar-show") ||
            $(e.target).hasClass("search-show")
          ) {
            $("body").removeClass("sidebar-show");
            $("body").addClass("sidebar-gone");
            $("body").removeClass("search-show");

            update_sidebar_nicescroll();
          }
        });

      update_sidebar_nicescroll();

      if (now_layout_class == "layout-3") {
        let nav_second_classes = $(".navbar-secondary").attr("class"),
          nav_second = $(".navbar-secondary");

        nav_second.attr("data-nav-classes", nav_second_classes);
        nav_second.removeAttr("class");
        nav_second.addClass("main-sidebar");

        let main_sidebar = $(".main-sidebar");
        main_sidebar
          .find(".container")
          .addClass("sidebar-wrapper")
          .removeClass("container");
        main_sidebar
          .find(".navbar-nav")
          .addClass("sidebar-menu")
          .removeClass("navbar-nav");
        main_sidebar.find(".sidebar-menu .nav-item.dropdown.show a").click();
        main_sidebar.find(".sidebar-brand").remove();
        main_sidebar.find(".sidebar-menu").before(
          $("<div>", {
            class: "sidebar-brand"
          }).append(
            $("<a>", {
              href: $(".navbar-brand").attr("href")
            }).html($(".navbar-brand").html())
          )
        );
        setTimeout(function () {
          sidebar_nicescroll = main_sidebar.niceScroll(sidebar_nicescroll_opts);
          sidebar_nicescroll = main_sidebar.getNiceScroll();
        }, 700);

        sidebar_dropdown();
        $(".main-wrapper").removeClass("container");
      }
    } else {
      $("body").removeClass("sidebar-gone sidebar-show");
      if (now_layout_class) $("body").addClass(now_layout_class);

      let nav_second_classes = $(".main-sidebar").attr("data-nav-classes"),
        nav_second = $(".main-sidebar");

      if (
        now_layout_class == "layout-3" &&
        nav_second.hasClass("main-sidebar")
      ) {
        nav_second.find(".sidebar-menu li a.has-dropdown").off("click");
        nav_second.find(".sidebar-brand").remove();
        nav_second.removeAttr("class");
        nav_second.addClass(nav_second_classes);

        let main_sidebar = $(".navbar-secondary");
        main_sidebar
          .find(".sidebar-wrapper")
          .addClass("container")
          .removeClass("sidebar-wrapper");
        main_sidebar
          .find(".sidebar-menu")
          .addClass("navbar-nav")
          .removeClass("sidebar-menu");
        main_sidebar.find(".dropdown-menu").hide();
        main_sidebar.removeAttr("style");
        main_sidebar.removeAttr("tabindex");
        main_sidebar.removeAttr("data-nav-classes");
        $(".main-wrapper").addClass("container");
        // if(sidebar_nicescroll != null)
        //   sidebar_nicescroll.remove();
      } else if (now_layout_class == "layout-2") {
        $("body").addClass("layout-2");
      } else {
        update_sidebar_nicescroll();
      }
    }
  };
  toggleLayout();
  $(window).resize(toggleLayout);

  $("[data-toggle='search']").click(function () {
    var body = $("body");

    if (body.hasClass("search-gone")) {
      body.addClass("search-gone");
      body.removeClass("search-show");
    } else {
      body.removeClass("search-gone");
      body.addClass("search-show");
    }
  });

  // tooltip
  $("[data-toggle='tooltip']").tooltip();

  // popover
  $('[data-toggle="popover"]').popover({
    container: "body"
  });

  // Enhanced selects (Tom Select + mm-tomselect-jquery.js)
  if (jQuery.fn.mmSelect) {
    $('.mm-select').mmSelect();
  }

  // Selectric
  if (jQuery().selectric) {
    $(".selectric").selectric({
      disableOnMobile: false,
      nativeOnMobile: false
    });
  }

  // Notification dropdown functionality removed - using page redirect instead
  // $(".notification-toggle").dropdown(); // Removed - not needed for redirect functionality
  // $(".notification-toggle")
  //   .parent()
  //   .on("shown.bs.dropdown", function () {
  //     $(".dropdown-list-icons").niceScroll({
  //       cursoropacitymin: 0.3,
  //       cursoropacitymax: 0.8,
  //       cursorwidth: 7
  //     });
  //   });

  $(".message-toggle").dropdown();
  $(".message-toggle")
    .parent()
    .on("shown.bs.dropdown", function () {
      $(".dropdown-list-message").niceScroll({
        cursoropacitymin: 0.3,
        cursoropacitymax: 0.8,
        cursorwidth: 7
      });
    });

  // TinyMCE Editor Initialization
  if (typeof tinymce !== 'undefined') {
    // Shared color map for both editor configurations
    var sharedColorMap = [
      "000000", "Black", "333333", "Dark Gray", "666666", "Medium Gray",
      "999999", "Light Gray", "CCCCCC", "Very Light Gray", "E0E0E0", "Pale Gray",
      "F5F5F5", "Off White", "FFFFFF", "White", "DC2626", "Red",
      "EA580C", "Orange", "D97706", "Amber", "059669", "Green",
      "0891B2", "Cyan", "2563EB", "Blue", "7C3AED", "Purple",
      "DB2777", "Pink", "EF4444", "Light Red", "F97316", "Light Orange",
      "F59E0B", "Light Amber", "10B981", "Light Green", "06B6D4", "Light Cyan",
      "3B82F6", "Light Blue", "8B5CF6", "Light Purple", "EC4899", "Light Pink"
    ];

    // Configuration for simple TinyMCE editors (.tinymce-editor)
    // Optimized for quick notes with essential features
    var tinymceSimpleConfig = {
      license_key: 'gpl',
      height: 150,
      min_height: 150,
      max_height: 400,
      menubar: false,
      statusbar: true,  // Show status bar for word count
      resize: true,     // Allow manual resizing
      plugins: [
        'lists',           // List support (bullets, numbers)
        'link',            // Link insertion/editing
        'autolink',        // Auto-convert URLs to links
        'autoresize',      // Auto-adjust height based on content
        'wordcount',       // Word/character count
        'searchreplace'    // Find and replace functionality
      ],
      toolbar: 'undo redo | bold italic underline strikethrough | forecolor backcolor | bullist numlist | link | removeformat',
      placeholder: 'Write your note here...',
      content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; padding: 12px 15px; }',
      branding: false,
      promotion: false,
      color_map: sharedColorMap,
      autoresize_bottom_margin: 10,
      // Paste handling
      paste_as_text: false,
      paste_remove_styles_if_webkit: true,
      smart_paste: true,
      // Accessibility
      a11y_advanced_options: true,
      contextmenu: false,  // Use native context menu
      setup: function(editor) {
        editor.on('change', function() {
          editor.save();
        });
      }
    };

    // Initialize TinyMCE for lightweight rich-text areas (.tinymce-editor)
    var editorsToInit = '.tinymce-editor';
    
    // Check if editors exist and initialize them
    $(editorsToInit).each(function() {
      var editorId = $(this).attr('id') || 'tinymce_' + Math.random().toString(36).substr(2, 9);
      if (!$(this).attr('id')) {
        $(this).attr('id', editorId);
      }
      
      // Only initialize if not already initialized
      if (tinymce.get(editorId) === null) {
        tinymce.init({
          ...tinymceSimpleConfig,
          selector: '#' + editorId
        });
      }
    });

    // Configuration for full TinyMCE editors (replacing .summernote)
    // Enhanced for emails, templates, and longer content
    var tinymceFullConfig = {
      license_key: 'gpl',
      height: 300,
      min_height: 300,
      max_height: 600,
      menubar: false,
      statusbar: true,  // Show status bar for word count and element path
      resize: true,     // Allow manual resizing
      plugins: [
        'lists',           // List support (bullets, numbers)
        'link',            // Link insertion/editing
        'autolink',        // Auto-convert URLs to links
        'autoresize',      // Auto-adjust height based on content
        'wordcount',       // Word/character count
        'table',           // Table insertion and editing
        'image',           // Image insertion
        'searchreplace',   // Find and replace functionality
        'code',            // View/edit HTML source
        'fullscreen',      // Distraction-free fullscreen mode
        'help'             // Built-in help dialog
      ],
      toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | \
                alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | \
                link image table | removeformat | code fullscreen | help',
      content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
      branding: false,
      promotion: false,
      color_map: sharedColorMap,
      autoresize_bottom_margin: 10,
      // Format options
      block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3; Preformatted=pre',
      style_formats: [
        {
          title: 'Headings',
          items: [
            { title: 'Heading 1', format: 'h1' },
            { title: 'Heading 2', format: 'h2' },
            { title: 'Heading 3', format: 'h3' }
          ]
        },
        {
          title: 'Inline',
          items: [
            { title: 'Bold', format: 'bold' },
            { title: 'Italic', format: 'italic' },
            { title: 'Underline', format: 'underline' },
            { title: 'Strikethrough', format: 'strikethrough' },
            { title: 'Code', format: 'code' }
          ]
        }
      ],
      // Paste handling
      paste_as_text: false,
      paste_data_images: true,  // Allow pasting images
      paste_remove_styles_if_webkit: true,
      smart_paste: true,
      // Image handling (configure with your upload endpoint)
      // images_upload_url: '/api/upload-editor-image',  // Uncomment and configure your endpoint
      // automatic_uploads: true,
      // images_reuse_filename: true,
      file_picker_types: 'image',
      images_file_types: 'jpg,jpeg,png,gif,webp',
      // Table options
      table_default_attributes: {
        border: '1'
      },
      table_default_styles: {
        'border-collapse': 'collapse',
        'width': '100%'
      },
      table_resize_bars: true,
      table_appearance_options: false,
      // Accessibility
      a11y_advanced_options: true,
      contextmenu: false,  // Use native context menu
      setup: function(editor) {
        editor.on('change', function() {
          editor.save();
        });
      }
    };

    // Initialize TinyMCE for .summernote (keeping class for backward compatibility)
    $('.summernote').each(function() {
      var editorId = $(this).attr('id') || 'tinymce_' + Math.random().toString(36).substr(2, 9);
      if (!$(this).attr('id')) {
        $(this).attr('id', editorId);
      }
      
      if (tinymce.get(editorId) === null) {
        tinymce.init({
          ...tinymceFullConfig,
          selector: '#' + editorId
        });
      }
    });
  }

  // Dismiss function
  $("[data-dismiss]").each(function () {
    var me = $(this),
      target = me.data("dismiss");

    me.click(function () {
      $(target).fadeOut(function () {
        $(target).remove();
      });
      return false;
    });
  });

  // Collapsable
  $("[data-collapse]").each(function () {
    var me = $(this),
      target = me.data("collapse");

    me.click(function () {
      $(target).collapse("toggle");
      $(target).on("shown.bs.collapse", function () {
        me.html((typeof crmIconAny === 'function' ? crmIconAny('fas fa-minus') : '<i class="fas fa-minus"></i>'));
      });
      $(target).on("hidden.bs.collapse", function () {
        me.html((typeof crmIconAny === 'function' ? crmIconAny('fas fa-plus') : '<i class="fas fa-plus"></i>'));
      });
      return false;
    });
  });

  // Background
  $("[data-background]").each(function () {
    var me = $(this);
    me.css({
      backgroundImage: "url(" + me.data("background") + ")"
    });
  });

  // Custom Tab
  $("[data-tab]").each(function () {
    var me = $(this);

    me.click(function () {
      if (!me.hasClass("active")) {
        var tab_group = $('[data-tab-group="' + me.data("tab") + '"]'),
          tab_group_active = $(
            '[data-tab-group="' + me.data("tab") + '"].active'
          ),
          target = $(me.attr("href")),
          links = $('[data-tab="' + me.data("tab") + '"]');

        links.removeClass("active");
        me.addClass("active");
        target.addClass("active");
        tab_group_active.removeClass("active");
      }
      return false;
    });
  });

  // Bootstrap 4 Validation
  $(".needs-validation").submit(function () {
    var form = $(this);
    if (form[0].checkValidity() === false) {
      event.preventDefault();
      event.stopPropagation();
    }
    form.addClass("was-validated");
  });

  // alert dismissible
  $(".alert-dismissible").each(function () {
    var me = $(this);

    me.find(".close").click(function () {
      me.alert("close");
    });
  });

  if ($(".main-navbar").length) {
  }

  // Image cropper
  $("[data-crop-image]").each(function (e) {
    $(this).css({
      overflow: "hidden",
      position: "relative",
      height: $(this).data("crop-image")
    });
  });

  // Slide Toggle
  $("[data-toggle-slide]").click(function () {
    let target = $(this).data("toggle-slide");

    $(target).slideToggle();
    return false;
  });

  // Dismiss modal (Bootstrap 5: data-bs-dismiss="modal")
  $("[data-bs-dismiss=modal], [data-dismiss=modal]").click(function () {
    $(this)
      .closest(".modal")
      .modal("hide");

    return false;
  });

  // Width attribute
  $("[data-width]").each(function () {
    $(this).css({
      width: $(this).data("width")
    });
  });

  // Height attribute
  $("[data-height]").each(function () {
    $(this).css({
      height: $(this).data("height")
    });
  });

  // Chocolat
  if ($(".chocolat-parent").length && jQuery().Chocolat) {
    $(".chocolat-parent").Chocolat();
  }

  // Sortable card
  if ($(".sortable-card").length && jQuery().sortable) {
    $(".sortable-card").sortable({
      handle: ".card-header",
      opacity: 0.8,
      tolerance: "pointer"
    });
  }

  // Date/time inputs: Flatpickr only. CSS classes (.datepicker, .datetimepicker, etc.) are legacy markup hooks — not jQuery plugins.
  if (typeof flatpickr !== 'undefined') {
    // CRM layouts load CRM_Flatpickr via Vite vendor-libs — skip duplicate .datepicker init there.
    var isClientDetailPage = $('.report_date_fields').length > 0 ||
                             $('.client-navigation-sidebar').length > 0;

    if (!isClientDetailPage) {
      // Initialize Flatpickr for all other pages
      if ($(".datepicker").length) {
        $(".datepicker").each(function() {
          var $this = $(this);
          if (!$this.data('flatpickr')) {
            flatpickr(this, {
              dateFormat: 'Y-m-d', // YYYY-MM-DD format for backend
              allowInput: true,
              clickOpens: true,
              locale: { firstDayOfWeek: 1 },
              onChange: function(selectedDates, dateStr, instance) {
                $this.val(dateStr);
                $this.trigger('change');
              }
            });
          }
        });
      }
    }
    
    // Initialize Flatpickr for DOB datepickers
    if ($(".dobdatepicker").length) {
      $(".dobdatepicker").each(function() {
        var $this = $(this);
        if (!$this.data('flatpickr')) {
          flatpickr(this, {
            dateFormat: 'd/m/Y', // DD/MM/YYYY format
            allowInput: true,
            clickOpens: true,
            maxDate: 'today',
            minDate: '01/01/1900',
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr, instance) {
              $this.val(dateStr);
              $this.trigger('change');
            }
          });
        }
      });
    }
    // Initialize Flatpickr for DOB datepickers with age calculation
    if ($(".dobdatepickers").length) {
      $(".dobdatepickers").each(function() {
        var $this = $(this);
        if (!$this.data('flatpickr')) {
          flatpickr(this, {
            dateFormat: 'd/m/Y', // DD/MM/YYYY format
            allowInput: true,
            clickOpens: true,
            maxDate: 'today',
            minDate: '01/01/1900',
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr, instance) {
              $this.val(dateStr);
              $this.trigger('change');
              
              // Calculate age
              if (dateStr && $('input[name="age"]').length) {
                var age = calculateAgeFromDDMMYYYY(dateStr);
                $('input[name="age"]').val(age);
              }
            }
          });
        }
      });
    }
    
    // Helper function to calculate age from DD/MM/YYYY format
    function calculateAgeFromDDMMYYYY(dateStr) {
      if (!dateStr || !/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) return '';
      
      try {
        var parts = dateStr.split('/');
        var day = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10) - 1; // 0-based
        var year = parseInt(parts[2], 10);
        
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var dob = new Date(year, month, day);
        
        if (isNaN(dob.getTime())) return '';
        
        var yearNow = now.getFullYear();
        var monthNow = now.getMonth();
        var dateNow = now.getDate();
        
        var yearDob = dob.getFullYear();
        var monthDob = dob.getMonth();
        var dateDob = dob.getDate();
        
        var yearAge = yearNow - yearDob;
        var monthAge, dateAge;
        
        if (monthNow >= monthDob) {
          monthAge = monthNow - monthDob;
        } else {
          yearAge--;
          monthAge = 12 + monthNow - monthDob;
        }
        
        if (dateNow >= dateDob) {
          dateAge = dateNow - dateDob;
        } else {
          monthAge--;
          dateAge = 31 + dateNow - dateDob;
          if (monthAge < 0) {
            monthAge = 11;
            yearAge--;
          }
        }
        
        var ageString = "";
        var yearString = (yearAge > 1) ? " years" : " year";
        var monthString = (monthAge > 1) ? " months" : " month";
        var dayString = (dateAge > 1) ? " days" : " day";
        
        if (yearAge > 0 && monthAge > 0 && dateAge > 0) {
          ageString = yearAge + yearString + " " + monthAge + monthString;
        } else if (yearAge == 0 && monthAge == 0 && dateAge > 0) {
          ageString = dateAge + dayString;
        } else if (yearAge > 0 && monthAge == 0 && dateAge == 0) {
          ageString = yearAge + yearString;
        } else if (yearAge > 0 && monthAge > 0 && dateAge == 0) {
          ageString = yearAge + yearString + " " + monthAge + monthString;
        } else if (yearAge == 0 && monthAge > 0 && dateAge > 0) {
          ageString = monthAge + monthString;
        } else if (yearAge > 0 && monthAge == 0 && dateAge > 0) {
          ageString = yearAge + yearString;
        } else if (yearAge == 0 && monthAge > 0 && dateAge == 0) {
          ageString = monthAge + monthString;
        } else {
          ageString = "Oops! Could not calculate age!";
        }
        
        return ageString;
      } catch (e) {
        console.error('Age calculation error:', e);
        return '';
      }
    }
    // Initialize Flatpickr for filter datepickers
    if ($(".filterdatepicker").length) {
      $(".filterdatepicker").each(function() {
        var $this = $(this);
        if (!$this.data('flatpickr')) {
          flatpickr(this, {
            dateFormat: 'Y-m-d', // YYYY-MM-DD format for backend
            allowInput: true,
            clickOpens: true,
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr, instance) {
              $this.val(dateStr);
              $this.trigger('change');
            }
          });
        }
      });
    }
    // Initialize Flatpickr for contract expiry datepickers
    if ($(".contract_expiry").length) {
      $(".contract_expiry").each(function() {
        var $this = $(this);
        if (!$this.data('flatpickr')) {
          flatpickr(this, {
            dateFormat: 'Y-m-d', // YYYY-MM-DD format for backend
            allowInput: true,
            clickOpens: true,
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr, instance) {
              $this.val(dateStr);
              $this.trigger('change');
            }
          });
        }
      });
    }
    // Class .datetimepicker is legacy HTML only; bound here with Flatpickr (date + time).
    if ($(".datetimepicker").length) {
      $(".datetimepicker").each(function() {
        var $this = $(this);
        if (!$this.data('flatpickr')) {
          flatpickr(this, {
            dateFormat: 'Y-m-d H:i', // YYYY-MM-DD HH:mm format
            allowInput: true,
            clickOpens: true,
            enableTime: true,
            time_24hr: true, // 24-hour format
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr, instance) {
              $this.val(dateStr);
              $this.trigger('change');
            }
          });
        }
      });
    }
    // Initialize Flatpickr for date range pickers
    if ($(".daterange").length) {
      $(".daterange").each(function() {
        var $this = $(this);
        if (!$this.data('flatpickr')) {
          flatpickr(this, {
            mode: 'range', // Enable range selection
            dateFormat: 'Y-m-d', // YYYY-MM-DD format for backend
            allowInput: true,
            clickOpens: true,
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr, instance) {
              if (selectedDates.length === 2) {
                // Format as "YYYY-MM-DD - YYYY-MM-DD"
                var startDate = instance.formatDate(selectedDates[0], 'Y-m-d');
                var endDate = instance.formatDate(selectedDates[1], 'Y-m-d');
                $this.val(startDate + ' - ' + endDate);
              } else if (selectedDates.length === 1) {
                $this.val(dateStr);
              } else {
                $this.val('');
              }
              $this.trigger('change');
            }
          });
        }
      });
    }
  } // End: if (typeof flatpickr !== 'undefined')

  $("#mini_sidebar_setting").on("change", function () {
    var _val = $(this).is(":checked") ? "checked" : "unchecked";
    if (_val === "checked") {
      toggle_sidebar_mini(true);
    } else {
      toggle_sidebar_mini(false);
    }
  });
  $("#sticky_header_setting").on("change", function () {
    if ($(".main-navbar")[0].classList.contains("sticky")) {
      toggle_sticky_header(false);
    } else {
      toggle_sticky_header(true);
    }
  });

  $(".theme-setting-toggle").on("click", function () {
    if ($(".theme-setting")[0].classList.contains("active")) {
      $(".theme-setting")[0].classList.remove("active");
    } else {
      $(".theme-setting")[0].classList += " active";
    }
  });

  // full screen call

  $(document).on("click", ".fullscreen-btn", function (e) {
    if (
      !document.fullscreenElement && // alternative standard method
      !document.mozFullScreenElement &&
      !document.webkitFullscreenElement &&
      !document.msFullscreenElement
    ) {
      // current working methods
      if (document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen();
      } else if (document.documentElement.msRequestFullscreen) {
        document.documentElement.msRequestFullscreen();
      } else if (document.documentElement.mozRequestFullScreen) {
        document.documentElement.mozRequestFullScreen();
      } else if (document.documentElement.webkitRequestFullscreen) {
        document.documentElement.webkitRequestFullscreen(
          Element.ALLOW_KEYBOARD_INPUT
        );
      }
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      } else if (document.msExitFullscreen) {
        document.msExitFullscreen();
      } else if (document.mozCancelFullScreen) {
        document.mozCancelFullScreen();
      } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
    }
  });

  // setting sidebar

  $(".settingPanelToggle").on("click", function () {
    $(".settingSidebar").toggleClass("showSettingPanel");
  }),
    $(".page-wrapper").on("click", function () {
      $(".settingSidebar").removeClass("showSettingPanel");
    });

  // close right sidebar when click outside
  var mouse_is_inside = false;
  $(".settingSidebar").hover(
    function () {
      mouse_is_inside = true;
    },
    function () {
      mouse_is_inside = false;
    }
  );

  $("body").mouseup(function () {
    if (!mouse_is_inside) $(".settingSidebar").removeClass("showSettingPanel");
  });

  $(".settingSidebar-body").niceScroll();

  // theme change event
  $(".choose-theme li").on("click", function () {
    var bodytag = $("body"),
      selectedTheme = $(this),
      prevTheme = $(".choose-theme li.active").attr("title");

    $(".choose-theme li").removeClass("active"),
      selectedTheme.addClass("active");
    $(".choose-theme li.active").data("theme");

    bodytag.removeClass("theme-" + prevTheme);
    bodytag.addClass("theme-" + $(this).attr("title"));
  });

  // dark light sidebar button setting
  $(".sidebar-color input:radio").change(function () {
    if ($(this).val() == "1") {
      $("body").removeClass("dark-sidebar");
      $("body").addClass("light-sidebar");
    } else {
      $("body").removeClass("light-sidebar");
      $("body").addClass("dark-sidebar");
    }
  });

  // dark light layout button setting
  $(".layout-color input:radio").change(function () {
    if ($(this).val() == "1") {
      $("body").removeClass();
      $("body").addClass("light");
      $("body").addClass("light-sidebar");
      $("body").addClass("theme-white");

      $(".choose-theme li").removeClass("active");
      $(".choose-theme li[title|='white']").addClass("active");
      $(".selectgroup-input[value|='1']").prop("checked", true);
    } else {
      $("body").removeClass();
      $("body").addClass("dark");
      $("body").addClass("dark-sidebar");
      $("body").addClass("theme-black");

      $(".choose-theme li").removeClass("active");
      $(".choose-theme li[title|='black']").addClass("active");
      $(".selectgroup-input[value|='2']").prop("checked", true);
    }
  });

  // restore default to dark theme
  $(".btn-restore-theme").on("click", function () {
    //remove all class from body
    $("body").removeClass();
    jQuery("body").addClass("light");
    jQuery("body").addClass("light-sidebar");
    jQuery("body").addClass("theme-white");

    // set default theme
    $(".choose-theme li").removeClass("active");
    $(".choose-theme li[title|='white']").addClass("active");

    $(".select-layout[value|='1']").prop("checked", true);
    $(".select-sidebar[value|='2']").prop("checked", true);
    toggle_sidebar_mini(false);
    $("#mini_sidebar_setting").prop("checked", false);
    $("#sticky_header_setting").prop("checked", true);
    toggle_sticky_header(true);
  });

  //start up class add

  //add default class on body tag
  jQuery("body").addClass("light");
  jQuery("body").addClass("light-sidebar");
  jQuery("body").addClass("theme-white");
  // set theme default color
  $(".choose-theme li").removeClass("active");
  $(".choose-theme li[title|='white']").addClass("active");
  //set default dark or light layout(1=light, 2=dark)
  $(".select-layout[value|='1']").prop("checked", true);
  //set default dark or light sidebar(1=light, 2=dark)
  $(".select-sidebar[value|='1']").prop("checked", true);
  // sticky header default set to true
  $("#sticky_header_setting").prop("checked", true);
});
