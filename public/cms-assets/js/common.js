var pageTitle = $("title").text();
var cmsAdminUi = window.CMS_ADMIN_UI || {};
var localPlaceholderImage = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="100%25" height="100%25" fill="%23f3f4f6"/><text x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" fill="%236b7280" font-family="sans-serif" font-size="18">' + encodeURIComponent(cmsAdminUi.noImage || 'No Image') + '</text></svg>';

function adminTemplate(template, values) {
    return Object.keys(values).reduce(function (message, key) {
        return message.split(':' + key).join(String(values[key]));
    }, template);
}

function bootstrapTableLocaleOptions() {
    if (!cmsAdminUi.tableLocale) {
        return {};
    }

    return {
        locale: cmsAdminUi.tableLocale,
        formatLoadingMessage: function () { return cmsAdminUi.tableLoading; },
        formatRecordsPerPage: function (count) {
            return adminTemplate(cmsAdminUi.tableRecordsPerPage, {count: count});
        },
        formatShowingRows: function (from, to, total, unfiltered) {
            var template = unfiltered && unfiltered > total
                ? cmsAdminUi.tableShowingRowsFiltered
                : cmsAdminUi.tableShowingRows;

            return adminTemplate(template, {from: from, to: to, total: total, unfiltered: unfiltered || total});
        },
        formatDetailPagination: function (count) {
            return adminTemplate(cmsAdminUi.tableDetailRows, {count: count});
        },
        formatSearch: function () { return cmsAdminUi.tableSearch; },
        formatNoMatches: function () { return cmsAdminUi.tableNoMatches; },
        formatAllRows: function () { return cmsAdminUi.tableAllRows; },
        formatClearSearch: function () { return cmsAdminUi.tableClearSearch; }
    };
}

function previewImage(e) {
    if (e.files && e.files[0]) {
        var t = new FileReader();
        (t.onload = function(e) {
            $(".previewImage").attr("src", e.target.result);
        }),
            t.readAsDataURL(e.files[0]);
    }
    $(".previewImage").hasClass("d-none") &&
    e.files.length > 0 &&
    $(".previewImage").removeClass("d-none").addClass("d-block");
}

var toastrOptions = {
    closeButton: !1,
    debug: !1,
    newestOnTop: !1,
    progressBar: !0,
    preventDuplicates: !1,
    onclick: null,
    showDuration: "300",
    hideDuration: "2500",
    timeOut: "2500",
    extendedTimeOut: "1000",
    showEasing: "swing",
    hideEasing: "linear",
    showMethod: "fadeIn",
    hideMethod: "fadeOut",
    "positionClass": "toast-center-center", // Ortada gösterim için sınıf
};

function successToast(e = cmsAdminUi.successMessage || "İşlem başarıyla gerçekleşti", t = "", hideDuration = 2500, p="top-center") {
    toastrOptions.hideDuration=hideDuration;
    toastrOptions.positionClass = 'toast-'+p;
    (toastr.options = toastrOptions), toastr.success(e, t);
}

function infoToast(e = cmsAdminUi.information || "Bilgilendirme", t = "", hideDuration = 2500, p="top-center") {
    toastrOptions.hideDuration=hideDuration;
    toastrOptions.positionClass = 'toast-'+p;
    (toastr.options = toastrOptions), toastr.info(e, t);
}

function warningToast(e = cmsAdminUi.warning || "Uyarı", t = "", hideDuration = 2500, p="top-center") {
    toastrOptions.hideDuration=hideDuration;
    toastrOptions.positionClass = 'toast-'+p;
    (toastr.options = toastrOptions), toastr.warning(e, t);
}

function errorToast(e = cmsAdminUi.error || "Hata", t = "", hideDuration = 2500, p="top-center") {
    toastrOptions.hideDuration=hideDuration;
    toastrOptions.positionClass = 'toast-'+p;
    (toastr.options = toastrOptions), toastr.error(e, t);
}

function toastMessage(statusCode=200,message="Info"){
    if(statusCode >= 100 && statusCode < 200){
        infoToast(message);
    }else if(statusCode >= 200 && statusCode < 300){
        successToast(message);
    }else if(statusCode >= 400 && statusCode <= 451){
        warningToast(message);
    }else if(statusCode >= 500){
        warningToast(message);
    }else{
        warningToast(message);
    }
}

function request(e = "POST", t, a = {}, i, s = null, n = null, c = null) {
    let token = $('meta[name="csrfToken"]').length > 0 ? $('meta[name="csrfToken"]').attr("content") : $('meta[name="csrf-token"]').attr("content");
    let options = c && typeof c === "object" ? c : {};
    let suppressGenericError = Boolean(options.suppressGenericError);
    let headers = Object.assign({
        "X-CSRF-TOKEN": token
    }, options.headers || {});

    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN":token
        },
    }),
        (a._token = token),
        $.ajax({
            url: t,
            type: e,
            beforeSend: s,
            headers: headers,
            error: function(xhr, textStatus, errorThrown) {
                if (typeof n === "function" && n(xhr, textStatus, errorThrown) === false) {
                    suppressGenericError = true;
                }
            },
            data: a,
            /*  processData: false,
             contentType: false, */
            complete: function(xhr) {
                if (200 !== xhr.status && !suppressGenericError) {
                    var response = null;

                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (error) {
                        response = null;
                    }

                    if (response && response.errors) {
                        $.each(response.errors, function(key, value) {
                            errorToast(value.join(", "));
                        });
                        return;
                    }

                    if (response && response.message) {
                        errorToast(response.message);
                        return;
                    }

                    errorToast(cmsAdminUi.requestFailedRefresh || "İstek tamamlanamadı. Lütfen sayfayı yenileyip tekrar deneyin.");
                }
            },
            success: function(e) {
                i(e),
                "notVerified" == e.status &&
                $("#verifyModal").modal("show");
            },
        });
}

function cmsAuthFailureCopy(key, fallback) {
    let copy = window.cmsAuthFeedbackCopy || {};
    let value = copy[key];

    return typeof value === "string" && value.trim().length > 0 ? value : fallback;
}

function cmsAuthFailurePayload(xhr) {
    if (!xhr || !xhr.responseText || typeof xhr.responseText !== "string") {
        return {};
    }

    try {
        let payload = JSON.parse(xhr.responseText);
        return payload && typeof payload === "object" ? payload : {};
    } catch (error) {
        return {};
    }
}

function cmsAuthFailureWithTrace(message, traceCode) {
    if (!traceCode) {
        return message;
    }

    let label = cmsAuthFailureCopy("traceCodeLabel", "Error code");

    return message.indexOf(traceCode) >= 0 ? message : message + " " + label + ": " + traceCode;
}

window.resolveCmsLoginFailure = function(xhr, fallbackTraceCode) {
    let payload = cmsAuthFailurePayload(xhr);
    let status = Number(xhr && xhr.status ? xhr.status : 0);
    let contentType = xhr && typeof xhr.getResponseHeader === "function"
        ? String(xhr.getResponseHeader("Content-Type") || "").toLowerCase()
        : "";
    let traceCode = String(payload.traceCode || fallbackTraceCode || "").trim().toUpperCase();
    let isJson = contentType.indexOf("json") >= 0 || Object.keys(payload).length > 0;

    if (status === 419) {
        return {
            kind: "session",
            reload: true,
            title: cmsAuthFailureCopy("sessionRefreshedTitle", "Session refreshed"),
            message: cmsAuthFailureCopy("sessionRefreshedText", "Session verification was refreshed. The page will reload, then you can try again.")
        };
    }

    if (status === 429) {
        return {
            kind: "rate_limited",
            title: cmsAuthFailureCopy("loginRateLimitedTitle", "Too many attempts"),
            message: String(payload.message || cmsAuthFailureCopy("loginRateLimited", "Too many login attempts. Please try again shortly.")).trim()
        };
    }

    if (status === 0 || !isJson) {
        return {
            kind: "connection",
            title: cmsAuthFailureCopy("loginConnectionTitle", "Connection problem"),
            message: cmsAuthFailureWithTrace(
                cmsAuthFailureCopy("loginConnectionFailure", "We could not reach the login service. Check your connection and try again."),
                traceCode
            )
        };
    }

    if (status >= 500) {
        return {
            kind: "unavailable",
            title: cmsAuthFailureCopy("loginUnavailableTitle", "Login temporarily unavailable"),
            message: cmsAuthFailureWithTrace(
                cmsAuthFailureCopy("loginUnavailable", "Login is temporarily unavailable. Please try again shortly."),
                traceCode
            )
        };
    }

    return {
        kind: "request",
        title: cmsAuthFailureCopy("checkDetailsTitle", "Check your details"),
        message: String(payload.message || cmsAuthFailureCopy("defaultMessage", "Check your details and try again.")).trim()
    };
};

function requestWithFile(e = "POST", t, a = {}, i, s = null, n = null) {
    let token = $("meta[name='csrfToken']").length > 0 ? $("meta[name='csrfToken']").attr("content") : $('meta[name="csrf-token"]').attr("content");

    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": token
        },
    }),
        (a._token = token),
        $.ajax({
            url: t,
            type: e,
            data: a,
            contentType: false,
            processData: false,
            async: !0,
            beforeSend: s,
            error: n,
            success: function(e) {
                i(e),
                "notVerified" == e.status &&
                $("#verifyModal").modal("show");
            },
        });
}

$(document).on("keypress", ".number", function(e) {
    if (8 != e.which && 0 != e.which && (e.which < 48 || e.which > 57))
        return !1;
});

$(function() {
    var table_length = $(".bootstrap-table").length;

    for (let i = 1; i < table_length+1; i++) {
        if(!$('.bootstrap-table-'+i).hasClass("noServer")){
            $('.bootstrap-table-'+i).bootstrapTable(Object.assign(bootstrapTableLocaleOptions(), {
                onLoadSuccess: function (number, size) {
                    $('[data-toggle="tooltip"]').tooltip();
                    $('.dropdown-toggle').dropdown();


                    $("img").on("error", function(){
                        $(this).attr("src", localPlaceholderImage);
                    });

                    return false;
                }
            }))
        }else{
            $('.bootstrap-table-'+i).bootstrapTable(bootstrapTableLocaleOptions());
        }
    }
    //
    // $(document).on("error", "img", function (){
    //     $(this).attr("src", "/assets/no-image.jpg");
    // })
})

var selectedIds = [];
$(document).on("change", '.selectedIds',function(){

    let _this = $(this);
    let selectedCount = $('.selectedIds:checked').length;
    selectedIds = [];
    let selectedIdsCommaSeparated = $.map($(".selectedIds:checked"), function (n, i){
        selectedIds.push(n.value);
        return n.value;
    }).join(",");
    $("#selectedIds").val(selectedIdsCommaSeparated);
    /*
    console.log("SELECTED IDS ");
    console.log(selectedIds);*/


    if(selectedCount > 0 ){
        $(".actionButton").prop("disabled", false);
    }else{
        $(".actionButton").prop("disabled", true);
    }
});
function checkboxFormatter(id){
    return '<input type="checkbox" name="selectedIds" value="'+id+'" class="selectedIds">';
}

function statusFormatter(status){
    if(status == "active"){
        return "<span class='bg-success p-2 rounded d-block text-white text-center'>"+(cmsAdminUi.active || "Aktif")+"</span>";
    }else if(status == "passive"){
        return "<span class='bg-danger p-2 rounded d-block text-white text-center'>"+(cmsAdminUi.passive || "Pasif")+"</span>";
    }else{
        return "<span class='bg-info p-2 rounded d-block text-black'>"+status+"</span>";
    }
}

function integerStatusFormatter(status){
    if(status == 1){
        return "<span class='bg-success p-2 rounded d-block text-white text-center'>"+(cmsAdminUi.active || "Aktif")+"</span>";
    }else if(status == 0){
        return "<span class='bg-danger p-2 rounded d-block text-white text-center'>"+(cmsAdminUi.passive || "Pasif")+"</span>";
    }else{
        return "<span class='bg-info p-2 rounded d-block text-black text-center'>"+status+"</span>";
    }
}
$(document).on("click", ".fileSelector", function (){
    $($(this).data("target")).trigger("click");
})

$(document).on("change", ".uploadImageWrapper input[type='file']", function (){

    $(this).parent().find("img").attr("src", window.URL.createObjectURL(this.files[0]))
});


function generateRandomString(charLength = 8) {
    // Basit bir rastgele kullanıcı adı üretme işlemi
    var characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var str = '';
    for (var i = 0; i < charLength; i++) {
        var randomIndex = Math.floor(Math.random() * characters.length);
        str += characters.charAt(randomIndex);
    }
    return str;
}
