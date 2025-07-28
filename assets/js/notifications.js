/**
 * TPAK DQ System - Notifications JavaScript
 * 
 * จัดการ real-time notifications และ AJAX polling
 */

(function($) {
    'use strict';
    
    // Notification system
    window.TPAKNotifications = {
        
        // Configuration
        config: {
            pollInterval: 30000, // 30 seconds
            maxNotifications: 10,
            autoDismiss: 5000, // 5 seconds
            notificationContainer: '#tpak-notifications-container'
        },
        
        // State
        state: {
            isPolling: false,
            lastNotificationId: 0,
            unreadCount: 0
        },
        
        // Initialize
        init: function() {
            this.createNotificationContainer();
            this.startPolling();
            this.bindEvents();
            this.loadInitialNotifications();
        },
        
        // Create notification container
        createNotificationContainer: function() {
            if ($(this.config.notificationContainer).length === 0) {
                $('body').append('<div id="tpak-notifications-container"></div>');
            }
        },
        
        // Start polling for new notifications
        startPolling: function() {
            if (this.state.isPolling) {
                return;
            }
            
            this.state.isPolling = true;
            this.pollNotifications();
        },
        
        // Poll for new notifications
        pollNotifications: function() {
            if (!this.state.isPolling) {
                return;
            }
            
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_get_notifications',
                    unread_only: true,
                    limit: this.config.maxNotifications,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.handleNotificationsResponse(response.data);
                    }
                }.bind(this),
                error: function() {
                    // Continue polling even on error
                },
                complete: function() {
                    // Schedule next poll
                    setTimeout(function() {
                        this.pollNotifications();
                    }.bind(this), this.config.pollInterval);
                }.bind(this)
            });
        },
        
        // Handle notifications response
        handleNotificationsResponse: function(data) {
            // Update unread count
            this.updateUnreadCount(data.unread_count);
            
            // Check for new notifications
            if (data.notifications && data.notifications.length > 0) {
                this.processNewNotifications(data.notifications);
            }
        },
        
        // Process new notifications
        processNewNotifications: function(notifications) {
            notifications.forEach(function(notification) {
                if (notification.id > this.state.lastNotificationId) {
                    this.showNotification(notification);
                    this.state.lastNotificationId = Math.max(this.state.lastNotificationId, notification.id);
                }
            }.bind(this));
        },
        
        // Show notification
        showNotification: function(notification) {
            const notificationHtml = this.createNotificationHtml(notification);
            const $notification = $(notificationHtml);
            
            $(this.config.notificationContainer).append($notification);
            
            // Animate in
            $notification.hide().slideDown(300);
            
            // Auto dismiss
            if (notification.type !== 'system_alert') {
                setTimeout(function() {
                    this.dismissNotification($notification);
                }.bind(this), this.config.autoDismiss);
            }
            
            // Play sound for important notifications
            if (notification.type === 'quality_check_failed' || notification.type === 'system_alert') {
                this.playNotificationSound();
            }
        },
        
        // Create notification HTML
        createNotificationHtml: function(notification) {
            const typeClass = this.getNotificationTypeClass(notification.type);
            const icon = this.getNotificationIcon(notification.type);
            
            return `
                <div class="tpak-notification ${typeClass}" data-notification-id="${notification.id}">
                    <div class="tpak-notification-content">
                        <div class="tpak-notification-icon">
                            <span class="dashicons ${icon}"></span>
                        </div>
                        <div class="tpak-notification-message">
                            <div class="tpak-notification-title">${this.escapeHtml(notification.title)}</div>
                            <div class="tpak-notification-text">${this.escapeHtml(notification.message)}</div>
                        </div>
                        <button class="tpak-notification-close" onclick="TPAKNotifications.dismissNotification($(this).closest('.tpak-notification'))">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                </div>
            `;
        },
        
        // Get notification type class
        getNotificationTypeClass: function(type) {
            const typeClasses = {
                'workflow_state_change': 'success',
                'task_assigned': 'info',
                'verification_completed': 'success',
                'quality_check_failed': 'error',
                'system_alert': 'warning',
                'report_ready': 'info'
            };
            
            return typeClasses[type] || 'info';
        },
        
        // Get notification icon
        getNotificationIcon: function(type) {
            const icons = {
                'workflow_state_change': 'dashicons-update',
                'task_assigned': 'dashicons-clipboard',
                'verification_completed': 'dashicons-yes-alt',
                'quality_check_failed': 'dashicons-warning',
                'system_alert': 'dashicons-megaphone',
                'report_ready': 'dashicons-chart-area'
            };
            
            return icons[type] || 'dashicons-bell';
        },
        
        // Dismiss notification
        dismissNotification: function($notification) {
            const notificationId = $notification.data('notification-id');
            
            // Mark as read
            this.markNotificationRead(notificationId);
            
            // Animate out
            $notification.slideUp(300, function() {
                $notification.remove();
            });
        },
        
        // Mark notification as read
        markNotificationRead: function(notificationId) {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_mark_notification_read',
                    notification_id: notificationId,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateUnreadCount(Math.max(0, this.state.unreadCount - 1));
                    }
                }.bind(this)
            });
        },
        
        // Update unread count
        updateUnreadCount: function(count) {
            this.state.unreadCount = count;
            
            // Update notification badge
            this.updateNotificationBadge(count);
            
            // Update admin bar if exists
            this.updateAdminBarBadge(count);
        },
        
        // Update notification badge
        updateNotificationBadge: function(count) {
            let $badge = $('.tpak-notification-badge');
            
            if (count > 0) {
                if ($badge.length === 0) {
                    $badge = $('<span class="tpak-notification-badge">' + count + '</span>');
                    $('.tpak-notification-toggle').append($badge);
                } else {
                    $badge.text(count);
                }
                $badge.show();
            } else {
                $badge.hide();
            }
        },
        
        // Update admin bar badge
        updateAdminBarBadge: function(count) {
            let $adminBarBadge = $('#wp-admin-bar-tpak-notifications .ab-label');
            
            if (count > 0) {
                if ($adminBarBadge.length === 0) {
                    // Create admin bar notification item if it doesn't exist
                    this.createAdminBarNotificationItem(count);
                } else {
                    $adminBarBadge.text('(' + count + ')');
                }
            } else {
                $('#wp-admin-bar-tpak-notifications').hide();
            }
        },
        
        // Create admin bar notification item
        createAdminBarNotificationItem: function(count) {
            const adminBarHtml = `
                <li id="wp-admin-bar-tpak-notifications">
                    <a href="#" class="ab-item">
                        <span class="ab-icon dashicons dashicons-bell"></span>
                        <span class="ab-label">(${count})</span>
                    </a>
                </li>
            `;
            
            $('#wp-admin-bar-top-secondary').prepend(adminBarHtml);
        },
        
        // Load initial notifications
        loadInitialNotifications: function() {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_get_notifications',
                    unread_only: true,
                    limit: this.config.maxNotifications,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.handleNotificationsResponse(response.data);
                    }
                }.bind(this)
            });
        },
        
        // Bind events
        bindEvents: function() {
            // Notification toggle
            $(document).on('click', '.tpak-notification-toggle', function(e) {
                e.preventDefault();
                this.toggleNotificationPanel();
            }.bind(this));
            
            // Mark all as read
            $(document).on('click', '.tpak-mark-all-read', function(e) {
                e.preventDefault();
                this.markAllNotificationsRead();
            }.bind(this));
            
            // Delete notification
            $(document).on('click', '.tpak-delete-notification', function(e) {
                e.preventDefault();
                const notificationId = $(this).data('notification-id');
                this.deleteNotification(notificationId);
            }.bind(this));
            
            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'n') {
                    e.preventDefault();
                    this.toggleNotificationPanel();
                }
            }.bind(this));
        },
        
        // Toggle notification panel
        toggleNotificationPanel: function() {
            const $panel = $('.tpak-notification-panel');
            
            if ($panel.length === 0) {
                this.createNotificationPanel();
            } else {
                $panel.toggle();
            }
        },
        
        // Create notification panel
        createNotificationPanel: function() {
            const panelHtml = `
                <div class="tpak-notification-panel">
                    <div class="tpak-notification-panel-header">
                        <h3>${tpak_dq_ajax.strings.notifications}</h3>
                        <button class="tpak-mark-all-read">${tpak_dq_ajax.strings.mark_all_read}</button>
                    </div>
                    <div class="tpak-notification-panel-content">
                        <div class="tpak-loading">${tpak_dq_ajax.strings.loading}</div>
                    </div>
                </div>
            `;
            
            $('body').append(panelHtml);
            this.loadNotificationPanel();
        },
        
        // Load notification panel
        loadNotificationPanel: function() {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_get_notifications',
                    limit: 20,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.renderNotificationPanel(response.data.notifications);
                    }
                }.bind(this)
            });
        },
        
        // Render notification panel
        renderNotificationPanel: function(notifications) {
            const $content = $('.tpak-notification-panel-content');
            
            if (notifications.length === 0) {
                $content.html('<p class="tpak-no-notifications">' + tpak_dq_ajax.strings.no_notifications + '</p>');
                return;
            }
            
            let html = '';
            notifications.forEach(function(notification) {
                const readClass = notification.is_read ? 'read' : 'unread';
                html += `
                    <div class="tpak-notification-item ${readClass}" data-notification-id="${notification.id}">
                        <div class="tpak-notification-item-header">
                            <span class="tpak-notification-item-title">${this.escapeHtml(notification.title)}</span>
                            <span class="tpak-notification-item-time">${this.formatTime(notification.created_at)}</span>
                        </div>
                        <div class="tpak-notification-item-message">${this.escapeHtml(notification.message)}</div>
                        <div class="tpak-notification-item-actions">
                            <button class="tpak-mark-read" data-notification-id="${notification.id}">${tpak_dq_ajax.strings.mark_read}</button>
                            <button class="tpak-delete-notification" data-notification-id="${notification.id}">${tpak_dq_ajax.strings.delete}</button>
                        </div>
                    </div>
                `;
            }.bind(this));
            
            $content.html(html);
        },
        
        // Mark all notifications as read
        markAllNotificationsRead: function() {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_mark_all_notifications_read',
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateUnreadCount(0);
                        $('.tpak-notification-item').addClass('read').removeClass('unread');
                    }
                }.bind(this)
            });
        },
        
        // Delete notification
        deleteNotification: function(notificationId) {
            $.ajax({
                url: tpak_dq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_delete_notification',
                    notification_id: notificationId,
                    nonce: tpak_dq_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('[data-notification-id="' + notificationId + '"]').remove();
                        this.updateUnreadCount(Math.max(0, this.state.unreadCount - 1));
                    }
                }.bind(this)
            });
        },
        
        // Play notification sound
        playNotificationSound: function() {
            // Create audio element for notification sound
            const audio = new Audio();
            audio.src = tpak_dq_ajax.plugin_url + 'assets/sounds/notification.mp3';
            audio.volume = 0.3;
            audio.play().catch(function() {
                // Ignore errors if audio can't be played
            });
        },
        
        // Format time
        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) { // Less than 1 minute
                return tpak_dq_ajax.strings.just_now;
            } else if (diff < 3600000) { // Less than 1 hour
                const minutes = Math.floor(diff / 60000);
                return minutes + ' ' + tpak_dq_ajax.strings.minutes_ago;
            } else if (diff < 86400000) { // Less than 1 day
                const hours = Math.floor(diff / 3600000);
                return hours + ' ' + tpak_dq_ajax.strings.hours_ago;
            } else {
                return date.toLocaleDateString();
            }
        },
        
        // Escape HTML
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // Stop polling
        stopPolling: function() {
            this.state.isPolling = false;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        TPAKNotifications.init();
    });
    
})(jQuery); 