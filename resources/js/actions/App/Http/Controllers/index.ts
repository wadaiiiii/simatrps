import DashboardController from './DashboardController'
import RpsController from './RpsController'
import RpsDeleteController from './RpsDeleteController'
import RpsDocumentController from './RpsDocumentController'
import ObeWorkspaceController from './ObeWorkspaceController'
import RpsCplScopeController from './RpsCplScopeController'
import RpsAiController from './RpsAiController'
import RpsAutomationController from './RpsAutomationController'
import RpsAssessmentController from './RpsAssessmentController'
import RpsTaskController from './RpsTaskController'
import Admin from './Admin'
import Settings from './Settings'
const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
RpsController: Object.assign(RpsController, RpsController),
RpsDeleteController: Object.assign(RpsDeleteController, RpsDeleteController),
RpsDocumentController: Object.assign(RpsDocumentController, RpsDocumentController),
ObeWorkspaceController: Object.assign(ObeWorkspaceController, ObeWorkspaceController),
RpsCplScopeController: Object.assign(RpsCplScopeController, RpsCplScopeController),
RpsAiController: Object.assign(RpsAiController, RpsAiController),
RpsAutomationController: Object.assign(RpsAutomationController, RpsAutomationController),
RpsAssessmentController: Object.assign(RpsAssessmentController, RpsAssessmentController),
RpsTaskController: Object.assign(RpsTaskController, RpsTaskController),
Admin: Object.assign(Admin, Admin),
Settings: Object.assign(Settings, Settings),
}

export default Controllers