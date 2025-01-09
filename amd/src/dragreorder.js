'use strict';
import $ from 'jquery';
import drag from 'core/dragdrop';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';
import {prefetchString} from 'core/prefetch';

export default class DragReorder {
    // Class variables handling state.
    config = {reorderStart: undefined, reorderEnd: undefined}; // Config object with some basic definitions.
    dragStart = null; // Information about when and where the drag started.
    originalOrder = null; // Array of ids that's used to compare the state after the drag event finishes.

    // DOM Nodes and jQuery representations.
    orderList = null; // Order list (HTMLElement).
    itemDragging = null; // Item being moved by dragging (jQuery object).
    proxy = null; // Drag proxy (jQuery object).

    constructor(config) {
        // Bring in the config to our state.
        this.config = config;
        this.sourceContainer = null;
        // Get the list we'll be working with this time.
        this.orderList = document.querySelector(this.config.list);

        this.startListeners();
    }

    /**
     * Start the listeners for the list.
     */
    startListeners() {
        /**
         * Handle mousedown or touchstart events on the list.
         *
         * @param {Event} e The event.
         */
        const pointerHandle = e => {
            if (e.target.closest(this.config.item) && !e.target.closest(this.config.actionButton)) {
                this.itemDragging = $(e.target.closest(this.config.item));
                const details = drag.prepare(e);
                if (details.start) {
                    this.startDrag(e, details);
                }
            }
        };
        // Set up the list listeners for moving list items around.
        this.orderList.addEventListener('mousedown', pointerHandle);
        this.orderList.addEventListener('touchstart', pointerHandle);
        this.orderList.addEventListener('click', this.itemMovedByClick.bind(this));
    }

    /**
     * Start dragging.
     *
     * @param {Event} e The event which is either mousedown or touchstart.
     * @param {Object} details Object with start (boolean flag) and x, y (only if flag true) values
     */
    startDrag(e, details) {
        this.dragStart = {
            time: new Date().getTime(),
            x: details.x,
            y: details.y
        };

        this.sourceContainer = this.itemDragging.closest('.sortablelist'); // Сохраняем исходный контейнер.

        if (typeof this.config.reorderStart !== 'undefined') {
            this.config.reorderStart(this.sourceContainer, this.itemDragging);
        }

        this.originalOrder = this.getCurrentOrder();

        Templates.renderForPromise('qtype_ddingroups/proxyhtml', {
            itemHtml: this.itemDragging.html(),
            itemClassName: this.itemDragging.attr('class'),
            listClassName: this.orderList.classList.toString(),
            proxyStyles: [
                `width: ${this.itemDragging.outerWidth()}px;`,
                `height: ${this.itemDragging.outerHeight()}px;`,
            ].join(' '),
        }).then(({html, js}) => {
            this.proxy = $(Templates.appendNodeContents(document.body, html, js)[0]);
            this.proxy.css(this.itemDragging.offset());

            this.itemDragging.addClass(this.config.itemMovingClass);

            this.updateProxy();
            drag.start(e, this.proxy, this.dragMove.bind(this), this.dragEnd.bind(this));
        }).catch(Notification.exception);
    }

    /**
     * Move the proxy to the current mouse position.
     */
    dragMove() {
        let targetGroup = null;
        // Определяем, над какой зоной (группой) находится элемент.
        document.querySelectorAll('.sortablelist').forEach(container => {
            const rect = container.getBoundingClientRect();
            const proxyRect = this.proxy[0].getBoundingClientRect();
            if (
                proxyRect.left > rect.left &&
                proxyRect.right < rect.right &&
                proxyRect.top > rect.top &&
                proxyRect.bottom < rect.bottom
            ) {
                targetGroup = container;
            }
        });
        // Сохраняем целевую группу, если она определена.
        this.targetGroup = targetGroup;
    }

    /**
     * Update proxy's position.
     */
    updateProxy() {
        const items = [...this.orderList.querySelectorAll(this.config.item)];
        for (let i = 0; i < items.length; ++i) {
            if (this.itemDragging[0] === items[i]) {
                this.proxy.find('li').attr('value', i + 1);
                break;
            }
        }
    }

    /**
     * End dragging and move item to appropriate group.
     */
    dragEnd() {
        if (typeof this.config.reorderEnd !== 'undefined') {
            this.config.reorderEnd(this.itemDragging.closest(this.config.list), this.itemDragging);
        }
        // Если элемент находится над допустимой зоной (группой), перемещаем его туда.
        if (this.targetGroup) {
            const groupList = this.targetGroup.querySelector('.group-answers');
            if (!groupList.contains(this.itemDragging[0])) {
                groupList.appendChild(this.itemDragging[0]);
            }
        } else {
            // Если элемент не находится над группой, возвращаем его в исходный контейнер.
            this.sourceContainer.append(this.itemDragging[0]);
        }
        // Обновляем JSON только если порядок изменился.
        if (!this.arrayEquals(this.originalOrder, this.getCurrentOrder())) {
            const currentGroup = this.itemDragging.closest('.group-box')?.id || 'general-box';
            const newOrder = this.getCurrentOrder().map(itemId => ({
                id: itemId,
                group: currentGroup,
            }));
            this.config.reorderDone(this.sourceContainer, this.itemDragging, newOrder);
            getString('moved', 'qtype_ddingroups', {
                item: this.itemDragging.find('[data-itemcontent]').text().trim(),
                position: this.itemDragging.index() + 1,
                total: this.orderList.querySelectorAll(this.config.item).length
            }).then((str) => {
                this.config.announcementRegion.innerHTML = str;
            });
        }
        // Убираем визуальные эффекты.
        this.proxy.remove();
        this.proxy = null;
        this.itemDragging.removeClass(this.config.itemMovingClass); // Удаляем класс после завершения перетаскивания.
        this.itemDragging = null;
        this.dragStart = null;
        this.targetGroup = null; // Сбрасываем целевую группу.
    }

    midX(node) {
        return node.offset().left + node.outerWidth() / 2;
    }
    midY(node) {
        return node.offset().top + node.outerHeight() / 2;
    }
    distanceBetweenElements(element) {
        const [e1, e2] = [$(element), $(this.proxy)];
        const [dx, dy] = [this.midX(e1) - this.midX(e2), this.midY(e1) - this.midY(e2)];
        return Math.sqrt(dx * dx + dy * dy);
    }
    getCurrentOrder() {
        return this.itemDragging.closest(this.config.list).find(this.config.item).map(
            (index, item) => {
                return this.config.idGetter(item);
            }).get();
    }
    arrayEquals(a1, a2) {
        return a1.length === a2.length &&
            a1.every((v, i) => {
                return v === a2[i];
            }); }
    static init(config) {
        const lists = config.lists;
        const responseid = config.responseid;
        lists.forEach(list => {
            new DragReorder({
                actionButton: '[data-action]',
                announcementRegion: document.querySelector(`#${list}-announcement`),
                list: `ul#${list}`,
                item: 'li.sortableitem',
                itemMovingClass: "current-drop",
                idGetter: item => {
                    return item.id;
                },
                // eslint-disable-next-line no-unused-vars
                reorderDone: (list, item, newOrder) => {
                    const response = {};
                    document.querySelectorAll('.group-box, #general-box').forEach(container => {
                        const groupId = container.id;
                        response[groupId] = Array.from(container.querySelectorAll('li.sortableitem')).map(item => item.id);
                    });

                    $('input#' + responseid)[0].value = JSON.stringify(response);
                }
            });
        });
        prefetchString('qtype_ddingroups', 'moved');
    }
}
