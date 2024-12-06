'use strict';

import $ from 'jquery';
import drag from 'core/dragdrop';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';
import {prefetchString} from 'core/prefetch';

export default class DragReorder {

    config = {reorderStart: undefined, reorderEnd: undefined};
    dragStart = null;
    originalOrder = null;

    groups = []; // Список контейнеров (групп).
    itemDragging = null;
    proxy = null;

    /**
     * Constructor.
     * @param {Object} config
     */
    constructor(config) {
        this.config = config;

        // Создаем группы из конфигурации.
        this.createGroups();

        this.startListeners();
    }

    /**
     * Создает группы на основе конфигурации.
     */
    createGroups() {
        const groupCount = this.config.groupCount || 2; // По умолчанию 2 группы.

        // Создаем группы и добавляем их в DOM.
        for (let i = 0; i < groupCount; i++) {
            const group = document.createElement('ul');
            group.classList.add('sortable-group');
            group.dataset.group = i + 1; // Уникальный идентификатор группы.
            this.config.parentContainer.appendChild(group); // Добавляем группы в контейнер.
            this.groups.push(group);
        }

        // Если в конфигурации уже есть элементы, распределяем их по группам.
        if (this.config.items) {
            this.config.items.forEach((item, index) => {
                const groupIndex = index % groupCount;
                const group = this.groups[groupIndex];
                group.appendChild(item);
            });
        }
    }

    /**
     * Начало слушателей событий.
     */
    startListeners() {
        const pointerHandle = e => {
            if (e.target.closest(this.config.item)) {
                this.itemDragging = $(e.target.closest(this.config.item));
                const details = drag.prepare(e);
                if (details.start) {
                    this.startDrag(e, details);
                }
            }
        };

        // Устанавливаем слушатели для всех групп.
        this.groups.forEach(group => {
            group.addEventListener('mousedown', pointerHandle);
            group.addEventListener('touchstart', pointerHandle);
        });
    }

    /**
     * Начинает процесс перетаскивания.
     * @param {Event} e
     * @param {Object} details
     */
    startDrag(e, details) {
        this.dragStart = {time: new Date().getTime(), x: details.x, y: details.y};

        if (typeof this.config.reorderStart !== 'undefined') {
            this.config.reorderStart(this.groups, this.itemDragging);
        }

        this.originalOrder = this.getCurrentOrder();

        Templates.renderForPromise('qtype_ddingroups/proxyhtml', {
            itemHtml: this.itemDragging.html(),
            itemClassName: this.itemDragging.attr('class'),
            proxyStyles: [
                `width: ${this.itemDragging.outerWidth()}px;`,
                `height: ${this.itemDragging.outerHeight()}px;`,
            ].join(' '),
        }).then(({html, js}) => {
            this.proxy = $(Templates.appendNodeContents(document.body, html, js)[0]);
            this.proxy.css(this.itemDragging.offset());

            drag.start(e, this.proxy, this.dragMove.bind(this), this.dragEnd.bind(this));
        }).catch(Notification.exception);
    }

    /**
     * Обрабатывает перемещение прокси-элемента.
     */
    dragMove() {
        let closestGroup = null;
        let closestDistance = null;

        // Определяем, какая группа находится ближе всего к текущему положению.
        this.groups.forEach(group => {
            const distance = this.distanceBetweenElements(group);
            if (closestGroup === null || distance < closestDistance) {
                closestGroup = group;
                closestDistance = distance;
            }
        });

        // Если ближайшая группа отличается от текущей, перемещаем элемент.
        if (closestGroup && closestGroup !== this.itemDragging.closest('.sortable-group')[0]) {
            closestGroup.appendChild(this.itemDragging[0]);
        }

        // Обновляем прокси.
        this.updateProxy();
    }

    /**
     * Завершает процесс перетаскивания.
     */
    dragEnd() {
        if (typeof this.config.reorderEnd !== 'undefined') {
            this.config.reorderEnd(this.groups, this.itemDragging);
        }

        if (!this.arrayEquals(this.originalOrder, this.getCurrentOrder())) {
            this.config.reorderDone(this.groups, this.itemDragging, this.getCurrentOrder());
        }

        this.proxy.remove();
        this.proxy = null;
        this.itemDragging = null;
        this.dragStart = null;
    }

    /**
     * Получает текущий порядок элементов во всех группах.
     * @returns {Array} массив массивов, где каждый подмассив — элементы группы.
     */
    getCurrentOrder() {
        return this.groups.map(group => {
            return Array.from(group.querySelectorAll(this.config.item)).map(item => this.config.idGetter(item));
        });
    }

    /**
     * Вычисляет расстояние между центрами элементов.
     * @param {HTMLElement} element
     * @return {number}
     */
    distanceBetweenElements(element) {
        const [e1, e2] = [$(element), $(this.proxy)];
        const [dx, dy] = [this.midX(e1) - this.midX(e2), this.midY(e1) - this.midY(e2)];
        return Math.sqrt(dx * dx + dy * dy);
    }

    /**
     * Инициализация компонента.
     * @param {String} containerId
     * @param {Object} config
     */
    static init(containerId, config) {
        const parentContainer = document.querySelector(`#${containerId}`);
        config.parentContainer = parentContainer;

        new DragReorder({
            ...config,
            list: '.sortable-group',
            item: 'li.sortableitem',
            itemMovingClass: 'current-drop',
            idGetter: item => item.id,
        });

        prefetchString('qtype_ddingroups', 'moved');
    }
}
