{{-- Task Detail Side Panel --}}
<div class="task-detail-panel" id="taskDetailPanel">
    <div class="task-detail-overlay" onclick="closeTaskDetail()"></div>
    
    <div class="task-detail-content">
        <div class="task-detail-header">
            <button class="task-detail-close" onclick="closeTaskDetail()">
                @icon('x', ['class' => 'icon-sm'])
            </button>
        </div>
        
        <div class="task-detail-body">
            <div class="task-detail-section">
                <div class="task-detail-complete">
                    <input type="checkbox" id="taskDetailComplete" class="task-detail-checkbox">
                    <label for="taskDetailComplete" class="task-detail-title" id="taskDetailTitle">
                        Task Title
                    </label>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    @icon('user', ['class' => 'detail-icon icon-sm'])
                    <div class="task-detail-info">
                        <div class="task-detail-label">Client</div>
                        <div class="task-detail-value">
                            <a href="#" id="taskDetailClientLink" class="task-client-link">
                                <span id="taskDetailClientName">Client Name</span>
                                <span id="taskDetailClientCode" class="task-detail-code"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    @icon('calendar', ['class' => 'detail-icon icon-sm'])
                    <div class="task-detail-info">
                        <div class="task-detail-label">Due Date</div>
                        <div class="task-detail-value" id="taskDetailDueDate">
                            Date
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    @icon('user-round', ['class' => 'detail-icon icon-sm'])
                    <div class="task-detail-info">
                        <div class="task-detail-label">Assigned To</div>
                        <div class="task-detail-value" id="taskDetailAssigned">
                            Assignee
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="task-detail-section">
                <div class="task-detail-row">
                    @icon('align-left', ['class' => 'detail-icon icon-sm'])
                    <div class="task-detail-info">
                        <div class="task-detail-label">Description</div>
                        <div class="task-detail-value task-detail-description" id="taskDetailDescription">
                            Description text
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="task-detail-footer">
            <button class="task-detail-action-btn btn-complete-task" onclick="completeTaskFromDetail()">
                @icon('check', ['class' => 'icon-sm'])
                Mark as Complete
            </button>
            <button class="task-detail-action-btn btn-extend-task" onclick="extendTaskFromDetail()">
                @icon('calendar-plus', ['class' => 'icon-sm'])
                Extend Deadline
            </button>
        </div>
    </div>
</div>

<style>
.task-detail-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 10000;
    display: none;
    pointer-events: none;
}

.task-detail-panel.active {
    display: block;
    pointer-events: all;
}

.task-detail-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.task-detail-panel.active .task-detail-overlay {
    opacity: 1;
}

.task-detail-content {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 420px;
    max-width: 100%;
    background: white;
    box-shadow: -2px 0 16px rgba(0, 0, 0, 0.1);
    transform: translateX(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.task-detail-panel.active .task-detail-content {
    transform: translateX(0);
}

.task-detail-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
}

.task-detail-close {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: #666;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.task-detail-close:hover {
    background: #f5f5f5;
    color: #333;
}

.task-detail-body {
    flex: 1;
    overflow-y: auto;
    padding: 24px 20px;
}

.task-detail-section {
    margin-bottom: 24px;
}

.task-detail-complete {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.task-detail-checkbox {
    appearance: none;
    width: 24px;
    height: 24px;
    border: 2px solid #c0c0c0;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    flex-shrink: 0;
    margin-top: 2px;
}

.task-detail-checkbox:hover {
    border-color: #2564cf;
}

.task-detail-checkbox:checked {
    background: #2564cf;
    border-color: #2564cf;
}

.task-detail-checkbox:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 16px;
    font-weight: bold;
}

.task-detail-title {
    font-size: 18px;
    font-weight: 500;
    color: #333;
    line-height: 1.4;
    cursor: pointer;
    flex: 1;
}

.task-detail-row {
    display: flex;
    gap: 12px;
}

.detail-icon {
    width: 20px;
    text-align: center;
    color: #666;
    margin-top: 2px;
    flex-shrink: 0;
}

.detail-icon.lucide,
svg.lucide.detail-icon {
    width: 20px;
    height: 20px;
}

.task-detail-info {
    flex: 1;
}

.task-detail-label {
    font-size: 12px;
    color: #999;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.task-detail-value {
    font-size: 14px;
    color: #333;
    line-height: 1.5;
}

.task-client-link {
    color: #2564cf;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.task-client-link:hover {
    text-decoration: underline;
}

.task-detail-code {
    color: #999;
    font-size: 13px;
}

.task-detail-description {
    white-space: pre-wrap;
    word-break: break-word;
}

.task-detail-footer {
    padding: 16px 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.task-detail-action-btn {
    width: 100%;
    padding: 12px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-complete-task {
    background: #28a745;
    color: white;
}

.btn-complete-task:hover {
    background: #218838;
}

.btn-extend-task {
    background: #f5f5f5;
    color: #333;
}

.btn-extend-task:hover {
    background: #e0e0e0;
}

@media (max-width: 768px) {
    .task-detail-content {
        width: 100%;
    }
}
</style>

