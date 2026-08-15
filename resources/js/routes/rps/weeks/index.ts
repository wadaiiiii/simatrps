import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import weight from './weight'
/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
export const update = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/rps/{rps}/weeks/{week}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
update.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    week: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                week: args.week,
                }

    return update.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
update.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
    const updateForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::update
 * @see app/Http/Controllers/ObeWorkspaceController.php:560
 * @route '/rps/{rps}/weeks/{week}'
 */
        updateForm.put = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
export const copyPrevious = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: copyPrevious.url(args, options),
    method: 'post',
})

copyPrevious.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/{week}/copy-previous',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
copyPrevious.url = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                    week: args[1],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                                week: args.week,
                }

    return copyPrevious.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace('{week}', parsedArgs.week.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
copyPrevious.post = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: copyPrevious.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
    const copyPreviousForm = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: copyPrevious.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::copyPrevious
 * @see app/Http/Controllers/RpsAutomationController.php:38
 * @route '/rps/{rps}/weeks/{week}/copy-previous'
 */
        copyPreviousForm.post = (args: { rps: string | number, week: string | number } | [rps: string | number, week: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: copyPrevious.url(args, options),
            method: 'post',
        })
    
    copyPrevious.form = copyPreviousForm
/**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
export const applyMethod = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyMethod.url(args, options),
    method: 'post',
})

applyMethod.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/apply-method',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
applyMethod.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return applyMethod.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
applyMethod.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyMethod.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
    const applyMethodForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: applyMethod.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\RpsAutomationController::applyMethod
 * @see app/Http/Controllers/RpsAutomationController.php:51
 * @route '/rps/{rps}/weeks/apply-method'
 */
        applyMethodForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: applyMethod.url(args, options),
            method: 'post',
        })
    
    applyMethod.form = applyMethodForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubcpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
export const alignSubcpmk = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: alignSubcpmk.url(args, options),
    method: 'post',
})

alignSubcpmk.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/align-subcpmk',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubcpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
alignSubcpmk.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return alignSubcpmk.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubcpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
alignSubcpmk.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: alignSubcpmk.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubcpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
    const alignSubcpmkForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: alignSubcpmk.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::alignSubcpmk
 * @see app/Http/Controllers/ObeWorkspaceController.php:420
 * @route '/rps/{rps}/weeks/align-subcpmk'
 */
        alignSubcpmkForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: alignSubcpmk.url(args, options),
            method: 'post',
        })
    
    alignSubcpmk.form = alignSubcpmkForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
export const applyTimeStandard = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyTimeStandard.url(args, options),
    method: 'post',
})

applyTimeStandard.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/apply-time-standard',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
applyTimeStandard.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return applyTimeStandard.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
applyTimeStandard.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: applyTimeStandard.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
    const applyTimeStandardForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: applyTimeStandard.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::applyTimeStandard
 * @see app/Http/Controllers/ObeWorkspaceController.php:463
 * @route '/rps/{rps}/weeks/apply-time-standard'
 */
        applyTimeStandardForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: applyTimeStandard.url(args, options),
            method: 'post',
        })
    
    applyTimeStandard.form = applyTimeStandardForm
/**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
export const normalizeReferences = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: normalizeReferences.url(args, options),
    method: 'post',
})

normalizeReferences.definition = {
    methods: ["post"],
    url: '/rps/{rps}/weeks/normalize-references',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
normalizeReferences.url = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { rps: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    rps: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        rps: args.rps,
                }

    return normalizeReferences.definition.url
            .replace('{rps}', parsedArgs.rps.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
normalizeReferences.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: normalizeReferences.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
    const normalizeReferencesForm = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: normalizeReferences.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\ObeWorkspaceController::normalizeReferences
 * @see app/Http/Controllers/ObeWorkspaceController.php:491
 * @route '/rps/{rps}/weeks/normalize-references'
 */
        normalizeReferencesForm.post = (args: { rps: string | number } | [rps: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: normalizeReferences.url(args, options),
            method: 'post',
        })
    
    normalizeReferences.form = normalizeReferencesForm
const weeks = {
    weight: Object.assign(weight, weight),
update: Object.assign(update, update),
copyPrevious: Object.assign(copyPrevious, copyPrevious),
applyMethod: Object.assign(applyMethod, applyMethod),
alignSubcpmk: Object.assign(alignSubcpmk, alignSubcpmk),
applyTimeStandard: Object.assign(applyTimeStandard, applyTimeStandard),
normalizeReferences: Object.assign(normalizeReferences, normalizeReferences),
}

export default weeks