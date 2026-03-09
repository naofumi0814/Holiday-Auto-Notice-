/**
 * Holiday Auto Notice - 管理画面JavaScript
 */
(function ($) {
    'use strict';

    const HAN = {
        /**
         * 初期化
         */
        init: function () {
            this.bindStatusChange();
            this.bindModalEvents();
            this.bindPreview();
            this.bindGenerate();
            this.bindManualRun();
            this.bindTestRun();
        },

        /**
         * ステータス変更時の連動
         */
        bindStatusChange: function () {
            $(document).on('change', '.han-status-select', function () {
                var $row = $(this).closest('tr');
                var status = $(this).val();
                var $typeSelect = $row.find('.han-type-select');
                var $generateBtn = $row.find('.han-generate-btn');
                var $previewBtn = $row.find('.han-preview-btn');

                // 休業種別の有効/無効
                if (status === 'holiday') {
                    $typeSelect.prop('disabled', false);
                    $generateBtn.prop('disabled', false);
                    $previewBtn.prop('disabled', false);
                } else {
                    $typeSelect.prop('disabled', true).val('');
                    $generateBtn.prop('disabled', true);
                    $previewBtn.prop('disabled', true);
                }

                // 行の色分けクラス更新
                $row.removeClass('han-holiday-row han-short-row han-special-row');
                if (status === 'holiday') $row.addClass('han-holiday-row');
                if (status === 'short') $row.addClass('han-short-row');
                if (status === 'special') $row.addClass('han-special-row');
            });
        },

        /**
         * モーダル制御
         */
        bindModalEvents: function () {
            // 閉じる
            $(document).on('click', '.han-modal-close', function () {
                $(this).closest('.han-modal').hide();
            });

            // 背景クリックで閉じる
            $(document).on('click', '.han-modal', function (e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });

            // ESCで閉じる
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $('.han-modal').hide();
                }
            });

            // 詳細ボタン
            $(document).on('click', '.han-edit-btn', function () {
                var date = $(this).data('date');
                HAN.openEditModal(date);
            });

            // モーダル保存
            $('#han-modal-save').on('click', function () {
                HAN.saveDay();
            });
        },

        /**
         * 詳細編集モーダルを開く
         */
        openEditModal: function (date) {
            $('#han-modal-date').val(date);
            $('#han-modal-title').text(date + ' 詳細編集');

            // 既存データがあればAJAXで取得、なければ空で表示
            // ここでは簡易的にフォームをクリアして表示
            $('#han-modal-custom-title').val('');
            $('#han-modal-custom-body').val('');
            $('#han-modal-custom-day-message').val('');

            $('#han-day-modal').show();
        },

        /**
         * 日付データ保存
         */
        saveDay: function () {
            var date = $('#han-modal-date').val();
            var $row = $('tr[data-date="' + date + '"]');

            var data = {
                action: 'han_save_day',
                nonce: hanAdmin.nonce,
                cal_date: date,
                status: $row.find('.han-status-select').val(),
                holiday_type: $row.find('.han-type-select').val() || '',
                memo: $row.find('.han-memo-input').val() || '',
                custom_title: $('#han-modal-custom-title').val(),
                custom_body: $('#han-modal-custom-body').val(),
                custom_day_message: $('#han-modal-custom-day-message').val(),
                auto_post: $row.find('input[name$="[auto_post]"]').is(':checked') ? 1 : 0
            };

            $.post(hanAdmin.ajaxUrl, data, function (response) {
                if (response.success) {
                    alert(response.data.message || '保存しました。');
                    $('#han-day-modal').hide();
                } else {
                    alert('エラー: ' + (response.data || '保存に失敗しました。'));
                }
            }).fail(function () {
                alert('通信エラーが発生しました。');
            });
        },

        /**
         * プレビュー
         */
        bindPreview: function () {
            $(document).on('click', '.han-preview-btn', function () {
                var date = $(this).data('date');
                var $row = $('tr[data-date="' + date + '"]');

                var data = {
                    action: 'han_preview',
                    nonce: hanAdmin.nonce,
                    cal_date: date,
                    status: $row.find('.han-status-select').val(),
                    holiday_type: $row.find('.han-type-select').val() || 'regular',
                    memo: $row.find('.han-memo-input').val() || ''
                };

                $.post(hanAdmin.ajaxUrl, data, function (response) {
                    if (response.success) {
                        var d = response.data;
                        var html = '<div class="han-preview-section">';
                        html += '<div class="han-preview-label">投稿タイトル</div>';
                        html += '<p>' + HAN.escapeHtml(d.title) + '</p>';
                        html += '</div>';

                        html += '<div class="han-preview-section">';
                        html += '<div class="han-preview-label">投稿本文</div>';
                        html += '<div>' + d.body + '</div>';
                        html += '</div>';

                        html += '<div class="han-preview-section">';
                        html += '<div class="han-preview-label">当日表示メッセージ</div>';
                        html += '<p>' + HAN.escapeHtml(d.day_message) + '</p>';
                        html += '</div>';

                        html += '<div class="han-preview-section">';
                        html += '<div class="han-preview-label">公開予定</div>';
                        html += '<p>ステータス: ' + HAN.escapeHtml(d.publish_date.status);
                        html += ' / 日時: ' + HAN.escapeHtml(d.publish_date.date) + '</p>';
                        html += '</div>';

                        $('#han-preview-content').html(html);
                        $('#han-preview-modal').show();
                    } else {
                        alert('プレビューの取得に失敗しました。');
                    }
                }).fail(function () {
                    alert('通信エラーが発生しました。');
                });
            });
        },

        /**
         * 個別投稿生成
         */
        bindGenerate: function () {
            $(document).on('click', '.han-generate-btn', function () {
                var $btn = $(this);
                var date = $btn.data('date');

                if (!confirm(date + ' の投稿を生成しますか？')) {
                    return;
                }

                $btn.prop('disabled', true).text('生成中...');

                $.post(hanAdmin.ajaxUrl, {
                    action: 'han_generate_single',
                    nonce: hanAdmin.nonce,
                    cal_date: date
                }, function (response) {
                    if (response.success) {
                        var d = response.data;
                        var html = '<a href="' + d.edit_url + '" target="_blank" class="han-post-link">#' + d.post_id + '</a>';
                        $btn.replaceWith(html);
                    } else {
                        alert('エラー: ' + (response.data || '生成に失敗しました。'));
                        $btn.prop('disabled', false).text('生成');
                    }
                }).fail(function () {
                    alert('通信エラーが発生しました。');
                    $btn.prop('disabled', false).text('生成');
                });
            });
        },

        /**
         * 手動実行
         */
        bindManualRun: function () {
            $('#han-manual-run').on('click', function () {
                if (!confirm('自動投稿の手動実行を行いますか？\n未生成の休業日投稿を生成し、既存投稿を再計算します。')) {
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('実行中...');

                $.post(hanAdmin.ajaxUrl, {
                    action: 'han_manual_run',
                    nonce: hanAdmin.nonce
                }, function (response) {
                    $btn.prop('disabled', false).text('手動実行');

                    if (response.success) {
                        var d = response.data;
                        var html = '<h3>実行結果</h3>';
                        d.messages.forEach(function (msg) {
                            html += '<div class="han-result-item">' + HAN.escapeHtml(msg) + '</div>';
                        });

                        $('#han-result-title').text('手動実行結果');
                        $('#han-result-content').html(html);
                        $('#han-result-modal').show();
                    } else {
                        alert('実行に失敗しました。');
                    }
                }).fail(function () {
                    $btn.prop('disabled', false).text('手動実行');
                    alert('通信エラーが発生しました。');
                });
            });
        },

        /**
         * テスト実行
         */
        bindTestRun: function () {
            $('#han-test-run').on('click', function () {
                var $btn = $(this);
                $btn.prop('disabled', true).text('テスト中...');

                $.post(hanAdmin.ajaxUrl, {
                    action: 'han_test_run',
                    nonce: hanAdmin.nonce
                }, function (response) {
                    $btn.prop('disabled', false).text('テスト実行');

                    if (response.success) {
                        var d = response.data;
                        var html = '<h3>テスト実行結果</h3>';

                        // 生成予定の投稿
                        html += '<h4>生成予定の投稿</h4>';
                        if (d.would_generate.length === 0) {
                            html += '<div class="han-result-item han-result-empty">生成対象なし</div>';
                        } else {
                            d.would_generate.forEach(function (item) {
                                html += '<div class="han-result-item">';
                                html += '<strong>' + HAN.escapeHtml(item.date) + '</strong>: ';
                                html += HAN.escapeHtml(item.title);
                                html += ' (公開: ' + HAN.escapeHtml(item.publish_date.status);
                                html += ' ' + HAN.escapeHtml(item.publish_date.date) + ')';
                                html += '</div>';
                            });
                        }

                        // 当日メッセージ
                        html += '<h4>本日の表示メッセージ</h4>';
                        html += '<div class="han-result-item">';
                        html += HAN.escapeHtml(d.today_message || '（メッセージなし）');
                        html += '</div>';

                        $('#han-result-title').text('テスト実行結果');
                        $('#han-result-content').html(html);
                        $('#han-result-modal').show();
                    } else {
                        alert('テスト実行に失敗しました。');
                    }
                }).fail(function () {
                    $btn.prop('disabled', false).text('テスト実行');
                    alert('通信エラーが発生しました。');
                });
            });
        },

        /**
         * HTMLエスケープ
         */
        escapeHtml: function (str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    $(document).ready(function () {
        HAN.init();
    });

})(jQuery);
