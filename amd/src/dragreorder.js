'use strict';
// eslint-disable-next-line no-unused-vars
import $ from 'jquery';
// eslint-disable-next-line no-unused-vars
import { getString } from 'core/str';
// eslint-disable-next-line no-unused-vars
import { prefetchString } from 'core/prefetch';

export default class DragReorder {
    constructor(config) {
        this.config = config;
        this.draggingElement = null; // Элемент, который перетаскивается.
        this.sourceContainer = null; // Исходная зона, из которой был взят элемент.
        this.lists = document.querySelectorAll(this.config.lists.join(',')); // Все зоны (группы + "Ответы").

        // Инициализация событий.
        this.initEventListeners();
    }

    /**
     * Инициализация обработчиков событий.
     */
    initEventListeners() {
        // Настройка событий для всех зон.
        this.lists.forEach(list => {
            list.addEventListener('dragover', this.handleDragOver.bind(this));
            list.addEventListener('drop', this.handleDrop.bind(this));
            list.addEventListener('dragleave', this.handleDragLeave.bind(this)); // Убираем подсветку.
        });

        // Настройка событий для всех элементов.
        const items = document.querySelectorAll(this.config.item);
        items.forEach(item => {
            item.addEventListener('dragstart', this.handleDragStart.bind(this));
            item.addEventListener('dragend', this.handleDragEnd.bind(this));
        });
    }

    /**
 * Обработка события `dragstart`.
 *
 * @param {DragEvent} e Событие перетаскивания.
 */
    handleDragStart(e) {
        this.draggingElement = e.target;
        this.sourceContainer = e.target.closest('ul.sortablelist');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', e.target.id);

        // Добавляем визуальный эффект.
        this.draggingElement.classList.add('dragging');
    }

    /**
 * Обработка события `dragend`.
 *
 */
    handleDragEnd() {
        if (this.draggingElement) {
            this.draggingElement.classList.remove('dragging'); // Убираем визуальный эффект.

            // Если элемент не попал в валидную зону, возвращаем его в исходную.
            if (!this.draggingElement.closest('.sortablelist')) {
                this.sourceContainer.appendChild(this.draggingElement);
            }

            // Обновляем JSON.
            this.updateResponse();
        }

        // Сбрасываем состояния.
        this.draggingElement = null;
        this.sourceContainer = null;
    }

    /**
 * Обработка события `dragover`.
 *
 * @param {DragEvent} e Событие перетаскивания.
 */
    handleDragOver(e) {
        e.preventDefault(); // Разрешаем перетаскивание.
        e.dataTransfer.dropEffect = 'move';

        // Подсвечиваем зону.
        const targetList = e.currentTarget;
        if (targetList.classList.contains('sortablelist')) {
            targetList.classList.add('drag-over');
        }
    }

    /**
 * Убираем подсветку при выходе из зоны.
 *
 * @param {DragEvent} e Событие перетаскивания.
 */
    handleDragLeave(e) {
        const targetList = e.currentTarget;
        if (targetList.classList.contains('sortablelist')) {
            targetList.classList.remove('drag-over');
        }
    }

    /**
 * Обработка события `drop`.
 *
 * @param {DragEvent} e Событие завершения перетаскивания.
 */
    handleDrop(e) {
        e.preventDefault();

        const targetList = e.currentTarget;
        if (!targetList.classList.contains('sortablelist')) {
            return;
        }

        // Перемещаем элемент.
        targetList.appendChild(this.draggingElement);

        // Убираем подсветку зоны.
        targetList.classList.remove('drag-over');

        // Обновляем JSON.
        this.updateResponse();
    }

    /**
     * Обновление JSON с результатами.
     */
    updateResponse() {
        const response = {};
        this.lists.forEach(list => {
            const groupId = list.id; // ID группы или зоны.
            response[groupId] = Array.from(list.querySelectorAll(this.config.item)).map(item => item.id);
        });

        // Записываем результаты в скрытое поле.
        const hiddenInput = document.querySelector(`#${this.config.responseid}`);
        hiddenInput.value = JSON.stringify(response);
    }

    /**
     * Инициализация класса DragReorder.
     * @param {DragEvent} config Событие перетаскивания.
     */
    static init(config) {
        new DragReorder(config);
    }
}
